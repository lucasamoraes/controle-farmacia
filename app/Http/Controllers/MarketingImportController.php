<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\MetaAdsSpreadsheetImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class MarketingImportController extends Controller
{
    public function create(): View
    {
        $company = $this->company();

        return view('marketing.import', [
            'company' => $company,
            'imports' => $company->marketingImports()->latest()->paginate(12),
            'metricsCount' => $company->marketingCampaignMetrics()->count(),
            'lastPeriod' => $company->marketingCampaignMetrics()->max('report_end'),
        ]);
    }

    public function store(Request $request, MetaAdsSpreadsheetImporter $importer): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validate([
            'spreadsheet' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);
        $file = $data['spreadsheet'];
        $path = $file->store('imports/marketing');
        $import = $company->marketingImports()->create([
            'user_id' => Auth::id(),
            'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
        ]);

        try {
            $stats = $importer->import($company, $import, storage_path('app/private/'.$path));
        } catch (Throwable $exception) {
            $import->delete();
            report($exception);

            return back()->withErrors(['spreadsheet' => $exception->getMessage()]);
        } finally {
            Storage::delete($path);
        }

        return redirect()->route('marketing.import.create')
            ->with('import_result', $stats)
            ->with('status', 'Dados do Meta Ads importados e painel de marketing atualizado.');
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
