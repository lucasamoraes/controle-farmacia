@extends('layouts.app', ['pageTitle' => 'Revisar boleto'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Revisar boleto</h1>
            <p class="subtitle">Confira os campos extraidos antes de criar a conta a pagar.</p>
        </div>
        <a class="btn secondary" href="{{ route('boletos.create') }}">Enviar outro PDF</a>
    </div>

    <div class="grid" style="grid-template-columns:minmax(0, 1fr) 320px; align-items:start; margin-top:22px;">
        <form class="form" method="post" action="{{ route('boletos.confirm', $boleto) }}" style="max-width:none;">
            @csrf

            @if ($duplicatePayables->isNotEmpty())
                <div class="card" style="background:#fff7ed; border-color:#fed7aa;">
                    <strong style="color:#9a3412;">Possivel boleto duplicado</strong>
                    <p class="subtitle" style="margin-top:6px;">Encontramos conta parecida ja cadastrada. Confira antes de confirmar.</p>
                    <div style="margin-top:12px; display:grid; gap:8px;">
                        @foreach ($duplicatePayables as $duplicate)
                            <div style="border-top:1px solid #fed7aa; padding-top:8px;">
                                <strong>{{ $duplicate->description }}</strong><br>
                                <span style="color:#7c2d12;">
                                    {{ $duplicate->due_date->format('d/m/Y') }} -
                                    R$ {{ number_format($duplicate->amount, 2, ',', '.') }} -
                                    {{ $duplicate->supplier->name ?? 'Sem fornecedor' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="field-grid">
                <label>Descricao
                    <input name="description" value="{{ old('description', $cnpjData['name'] ?? $parsed['beneficiary_name'] ?? $boleto->original_file_name) }}" required>
                    @error('description') <span class="error">{{ $message }}</span> @enderror
                </label>
                <label>Valor
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $parsed['amount'] ?? '') }}" required>
                    @error('amount') <span class="error">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="field-grid">
                <label>Vencimento
                    <input type="date" name="due_date" value="{{ old('due_date', $parsed['due_date'] ?? '') }}" required>
                    @error('due_date') <span class="error">{{ $message }}</span> @enderror
                </label>
                <label>Fornecedor
                    <select name="supplier_id">
                        <option value="">{{ $suggestedSupplier ? 'Sem fornecedor' : 'Criar ou selecionar fornecedor' }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $suggestedSupplier->id ?? '') === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id') <span class="error">{{ $message }}</span> @enderror
                </label>
            </div>

            <label>CNPJ/CPF do fornecedor
                <input name="document" value="{{ old('document', $parsed['document'] ?? '') }}" placeholder="Somente numeros ou com pontuacao">
                @error('document') <span class="error">{{ $message }}</span> @enderror
            </label>

            <div class="card" style="background:#f8fafc;">
                <strong>Vinculo do fornecedor</strong>
                <p class="subtitle" style="margin-top:6px;">Se o CNPJ estiver correto, escolha se deseja usar um fornecedor existente ou criar um novo automaticamente.</p>
                @if ($suggestedSupplier)
                    <p class="subtitle" style="margin-top:8px;">Fornecedor sugerido pelo CNPJ extraido: <strong style="color:var(--ink);">{{ $suggestedSupplier->name }}</strong>.</p>
                @endif
                <label style="display:flex; align-items:flex-start; gap:8px; font-weight:400; margin-top:12px;">
                    <input type="checkbox" name="create_supplier" value="1" @checked(! $suggestedSupplier) style="width:auto; margin-top:3px;">
                    <span>Criar fornecedor automaticamente se nenhum fornecedor for selecionado.</span>
                </label>
                <label style="display:flex; align-items:flex-start; gap:8px; font-weight:400;">
                    <input type="checkbox" name="link_document_to_supplier" value="1" checked style="width:auto; margin-top:3px;">
                    <span>Se eu selecionar um fornecedor existente, vincular o CNPJ digitado a ele.</span>
                </label>
            </div>

            <div class="field-grid">
                <label>Categoria
                    <select name="financial_category_id">
                        <option value="">Sem categoria</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('financial_category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('financial_category_id') <span class="error">{{ $message }}</span> @enderror
                </label>
                <label>Numero do documento
                    <input name="document_number" value="{{ old('document_number') }}">
                    @error('document_number') <span class="error">{{ $message }}</span> @enderror
                </label>
            </div>

            <label>Linha digitavel
                <input name="digitable_line" value="{{ old('digitable_line', $parsed['digitable_line'] ?? '') }}">
                @error('digitable_line') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>Observacoes
                <textarea name="notes">{{ old('notes') }}</textarea>
                @error('notes') <span class="error">{{ $message }}</span> @enderror
            </label>

            <div class="actions">
                <button class="btn" type="submit">Confirmar conta</button>
                <a class="btn secondary" href="{{ route('contas-a-pagar.create') }}">Cadastrar manualmente</a>
            </div>
        </form>

        <aside class="card">
            <h2 class="panel-title">Dados encontrados</h2>
            <div style="display:grid; gap:12px; color:var(--muted);">
                <div><strong style="color:var(--ink);">Arquivo</strong><br>{{ $boleto->original_file_name }}</div>
                <div><strong style="color:var(--ink);">CNPJ/CPF extraido</strong><br>{{ $parsed['document'] ?? '-' }}</div>
                <div><strong style="color:var(--ink);">Beneficiario no PDF</strong><br>{{ $parsed['beneficiary_name'] ?? '-' }}</div>
                <div><strong style="color:var(--ink);">Fornecedor existente</strong><br>{{ $suggestedSupplier->name ?? '-' }}</div>
                <div><strong style="color:var(--ink);">Possiveis duplicados</strong><br>{{ $duplicatePayables->count() }}</div>
                <div><strong style="color:var(--ink);">Status</strong><br>{{ $boleto->processing_status }}</div>
            </div>

            @if ($cnpjData)
                <hr style="border:0; border-top:1px solid var(--line); margin:16px 0;">
                <h2 class="panel-title">Consulta CNPJ</h2>
                <div style="display:grid; gap:10px; color:var(--muted);">
                    <div><strong style="color:var(--ink);">Razao social</strong><br>{{ $cnpjData['name'] ?? '-' }}</div>
                    <div><strong style="color:var(--ink);">Fantasia</strong><br>{{ $cnpjData['trade_name'] ?? '-' }}</div>
                    <div><strong style="color:var(--ink);">Situacao</strong><br>{{ $cnpjData['legal_status'] ?? '-' }}</div>
                    <div><strong style="color:var(--ink);">Cidade/UF</strong><br>{{ $cnpjData['city'] ?? '-' }}/{{ $cnpjData['state'] ?? '-' }}</div>
                </div>
            @endif
        </aside>
    </div>

    <div class="card" style="margin-top:18px;">
        <h2 class="panel-title">Texto extraido</h2>
        <textarea readonly style="min-height:220px; font-family:Consolas, monospace; font-size:12px;">{{ $boleto->extracted_text }}</textarea>
    </div>
@endsection

