<?php

namespace App\Http\Controllers;

use App\Models\BoletoUpload;
use App\Models\Company;
use App\Models\Supplier;
use App\Services\BoletoParser;
use App\Services\CnpjLookupService;
use App\Services\FinancialAlertService;
use App\Services\OcrService;
use App\Services\PdfTextExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class BoletoUploadController extends Controller
{
    public function create(): View
    {
        return view('boletos.create', [
            'company' => $this->company(),
        ]);
    }

    public function store(Request $request, PdfTextExtractor $extractor, BoletoParser $boletoParser, CnpjLookupService $cnpjLookup, OcrService $ocrService): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validate([
            'boleto_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $data['boleto_pdf'];
        $path = $file->store("boletos/{$company->id}");

        $upload = $company->boletoUploads()->create([
            'original_file_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'processing_status' => 'processing',
        ]);

        return $this->processUpload($upload, $extractor, $boletoParser, $cnpjLookup, $ocrService);
    }

    public function password(BoletoUpload $boleto): View
    {
        $company = $this->company();
        abort_unless($boleto->company_id === $company->id, 404);

        return view('boletos.password', [
            'company' => $company,
            'boleto' => $boleto,
        ]);
    }

    public function unlock(Request $request, BoletoUpload $boleto, PdfTextExtractor $extractor, BoletoParser $boletoParser, CnpjLookupService $cnpjLookup, OcrService $ocrService): RedirectResponse
    {
        $company = $this->company();
        abort_unless($boleto->company_id === $company->id, 404);

        $data = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        return $this->processUpload($boleto, $extractor, $boletoParser, $cnpjLookup, $ocrService, $data['password']);
    }

    public function review(BoletoUpload $boleto): View
    {
        $company = $this->company();
        abort_unless($boleto->company_id === $company->id, 404);

        return view('boletos.review', $this->reviewData($company, $boleto));
    }

    public function confirm(Request $request, BoletoUpload $boleto, CnpjLookupService $cnpjLookup, FinancialAlertService $alerts): RedirectResponse
    {
        $company = $this->company();
        abort_unless($boleto->company_id === $company->id, 404);

        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'create_supplier' => ['nullable', 'boolean'],
            'link_document_to_supplier' => ['nullable', 'boolean'],
            'document' => ['nullable', 'string', 'max:20'],
            'financial_category_id' => ['nullable', 'exists:financial_categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'digitable_line' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->applyDocumentCorrection($boleto, $data['document'] ?? null, $cnpjLookup);
        $data['digitable_line'] = $this->digitsOnly($data['digitable_line'] ?? null);
        $supplierId = $this->supplierIdForConfirmation($company, $boleto, $data);
        unset($data['create_supplier'], $data['link_document_to_supplier'], $data['document']);
        $data['supplier_id'] = $supplierId;

        $payable = $company->payables()->create($data + [
            'status' => 'open',
            'source' => 'boleto_pdf',
            'attachment_path' => $boleto->stored_path,
        ]);

        $boleto->update([
            'payable_id' => $payable->id,
            'processing_status' => 'confirmed',
        ]);

        $redirect = redirect()->route('contas-a-pagar.index')->with('status', 'Boleto confirmado e conta a pagar criada.');
        $alert = $alerts->currentMonthBoletoAlert($company, $payable);

        return $alert ? $redirect->with('app_alert', $alert) : $redirect;
    }

    private function processUpload(BoletoUpload $upload, PdfTextExtractor $extractor, BoletoParser $boletoParser, CnpjLookupService $cnpjLookup, OcrService $ocrService, ?string $password = null): RedirectResponse
    {
        try {
            $result = $extractor->extract(Storage::path($upload->stored_path), $password);

            if (($result['password_required'] ?? false) === true) {
                $upload->update([
                    'processing_status' => 'password_required',
                    'error_message' => $result['error'] ?? 'PDF protegido por senha.',
                ]);

                return redirect()->route('boletos.password', $upload);
            }

            $text = $result['text'] ?? '';
            $usedOcr = false;

            if ($this->needsOcr($text)) {
                if (! $ocrService->isEnabled()) {
                    $upload->update([
                        'extracted_text' => $text,
                        'parsed_data' => ['ocr_required' => true],
                        'processing_status' => 'failed',
                        'error_message' => 'O PDF parece ser escaneado. Configure o OCR para ler boletos em imagem.',
                    ]);

                    return redirect()->route('boletos.create')->with('status', 'O PDF parece ser escaneado. Cadastre manualmente por enquanto ou configure o OCR.');
                }

                $text = $ocrService->extractText(Storage::path($upload->stored_path));
                $usedOcr = true;
            }

            $parsedData = $boletoParser->parseText($text);
            $cnpjData = $cnpjLookup->lookup($parsedData['document'] ?? null);

            $upload->update([
                'extracted_text' => $text,
                'parsed_data' => $parsedData + ['cnpj_lookup' => $cnpjData, 'ocr_used' => $usedOcr],
                'processing_status' => 'review',
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $upload->update([
                'processing_status' => $password ? 'password_required' : 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            if ($password) {
                return redirect()->route('boletos.password', $upload)->with('status', 'Nao foi possivel abrir o PDF com essa senha. Verifique a senha ou tente cadastrar manualmente.');
            }

            return redirect()->route('boletos.create')->with('status', 'Nao foi possivel ler o PDF. Cadastre a conta manualmente ou tente outro arquivo.');
        }

        return redirect()->route('boletos.review', $upload);
    }

    private function needsOcr(?string $text): bool
    {
        $text = trim((string) $text);

        if (mb_strlen($text) < 40) {
            return true;
        }

        return ! preg_match('/\d{5}\.?\d{5}\s+\d{5}\.?\d{6}|vencimento|cedente|beneficiario|beneficiario/i', $text);
    }

    private function reviewData(Company $company, BoletoUpload $boleto): array
    {
        $parsed = $boleto->parsed_data ?? [];
        $document = $parsed['document'] ?? null;
        $supplier = $document ? $company->suppliers()->where('document', $document)->first() : null;
        $cnpjData = $parsed['cnpj_lookup'] ?? null;
        $duplicatePayables = $this->duplicateCandidates($company, $parsed);

        return [
            'company' => $company,
            'boleto' => $boleto,
            'parsed' => $parsed,
            'cnpjData' => $cnpjData,
            'suggestedSupplier' => $supplier,
            'duplicatePayables' => $duplicatePayables,
            'suppliers' => $company->suppliers()->where('is_active', true)->orderBy('name')->get(),
            'categories' => $company->categories()->where('type', 'expense')->orderBy('name')->get(),
        ];
    }

    private function applyDocumentCorrection(BoletoUpload $boleto, ?string $document, CnpjLookupService $cnpjLookup): void
    {
        $digits = preg_replace('/\D+/', '', (string) $document);
        $parsed = $boleto->parsed_data ?? [];

        if ($digits === '') {
            unset($parsed['document']);
            $boleto->update(['parsed_data' => $parsed]);

            return;
        }

        $parsed['document'] = $digits;

        if (($parsed['cnpj_lookup']['document'] ?? null) !== $digits) {
            $parsed['cnpj_lookup'] = $cnpjLookup->lookup($digits);
        }

        $boleto->update(['parsed_data' => $parsed]);
    }

    private function duplicateCandidates(Company $company, array $parsed)
    {
        $digitableLine = $this->digitsOnly($parsed['digitable_line'] ?? null);
        $document = $parsed['document'] ?? null;
        $amount = $parsed['amount'] ?? null;
        $dueDate = $parsed['due_date'] ?? null;

        if ($digitableLine === '' && ! ($document && $amount && $dueDate)) {
            return collect();
        }

        return $company->payables()
            ->with('supplier')
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($digitableLine, $document, $amount, $dueDate) {
                if ($digitableLine !== '') {
                    $query->orWhere('digitable_line', $digitableLine);
                }

                if ($document && $amount && $dueDate) {
                    $query->orWhere(function ($inner) use ($document, $amount, $dueDate) {
                        $inner->whereDate('due_date', $dueDate)
                            ->where('amount', $amount)
                            ->whereHas('supplier', fn ($supplier) => $supplier->where('document', $document));
                    });
                }
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    private function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function supplierIdForConfirmation(Company $company, BoletoUpload $boleto, array $data): ?int
    {
        if (! empty($data['supplier_id'])) {
            $supplier = $company->suppliers()->whereKey($data['supplier_id'])->firstOrFail();
            $this->fillSupplierDocumentFromBoleto($supplier, $boleto, ! empty($data['link_document_to_supplier']));

            return $supplier->id;
        }

        if (empty($data['create_supplier'])) {
            return null;
        }

        $parsed = $boleto->parsed_data ?? [];
        $cnpjData = $parsed['cnpj_lookup'] ?? null;
        $document = $parsed['document'] ?? ($cnpjData['document'] ?? null);

        if (! $document) {
            return null;
        }

        $existing = $company->suppliers()->where('document', $document)->first();

        if ($existing) {
            return $existing->id;
        }

        $supplier = $company->suppliers()->create([
            'financial_category_id' => $data['financial_category_id'] ?? null,
            'name' => $cnpjData['name'] ?? $parsed['beneficiary_name'] ?? $data['description'],
            'trade_name' => $cnpjData['trade_name'] ?? null,
            'document' => $document,
            'legal_status' => $cnpjData['legal_status'] ?? null,
            'email' => $cnpjData['email'] ?? null,
            'phone' => $cnpjData['phone'] ?? null,
            'street' => $cnpjData['street'] ?? null,
            'number' => $cnpjData['number'] ?? null,
            'district' => $cnpjData['district'] ?? null,
            'city' => $cnpjData['city'] ?? null,
            'state' => $cnpjData['state'] ?? null,
            'zip_code' => $cnpjData['zip_code'] ?? null,
            'main_activity' => $cnpjData['main_activity'] ?? null,
            'cnpj_checked_at' => $cnpjData ? now() : null,
        ]);

        return $supplier->id;
    }

    private function fillSupplierDocumentFromBoleto(Supplier $supplier, BoletoUpload $boleto, bool $forceLink): void
    {
        $parsed = $boleto->parsed_data ?? [];
        $cnpjData = $parsed['cnpj_lookup'] ?? null;
        $document = $parsed['document'] ?? ($cnpjData['document'] ?? null);

        if (! $document || ($supplier->document && ! $forceLink)) {
            return;
        }

        $supplier->update([
            'document' => $document,
            'legal_status' => $supplier->legal_status ?: ($cnpjData['legal_status'] ?? null),
            'email' => $supplier->email ?: ($cnpjData['email'] ?? null),
            'phone' => $supplier->phone ?: ($cnpjData['phone'] ?? null),
            'street' => $supplier->street ?: ($cnpjData['street'] ?? null),
            'number' => $supplier->number ?: ($cnpjData['number'] ?? null),
            'district' => $supplier->district ?: ($cnpjData['district'] ?? null),
            'city' => $supplier->city ?: ($cnpjData['city'] ?? null),
            'state' => $supplier->state ?: ($cnpjData['state'] ?? null),
            'zip_code' => $supplier->zip_code ?: ($cnpjData['zip_code'] ?? null),
            'main_activity' => $supplier->main_activity ?: ($cnpjData['main_activity'] ?? null),
            'cnpj_checked_at' => $cnpjData ? now() : $supplier->cnpj_checked_at,
        ]);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
