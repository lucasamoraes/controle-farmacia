<div class="field-grid">
    <label>Descricao
        <input name="description" value="{{ old('description', $payable->description ?? '') }}" required>
        @error('description') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>Valor
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $payable->amount ?? '') }}" required>
        @error('amount') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="field-grid">
    <label>Vencimento
        <input type="date" name="due_date" value="{{ old('due_date', isset($payable) ? $payable->due_date->format('Y-m-d') : '') }}" required>
        @error('due_date') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>Tipo da conta
        @php $accountType = old('account_type', $payable->account_type ?? 'boleto'); @endphp
        <select name="account_type">
            <option value="boleto" @selected($accountType === 'boleto')>Conta / boleto</option>
            <option value="credit_card" @selected($accountType === 'credit_card')>Cartao de credito</option>
        </select>
        @error('account_type') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>


<div class="field-grid">
    <label>Status
        <select name="status">
            @php $statusValue = old('status', $payable->status ?? 'open'); @endphp
            <option value="open" @selected($statusValue === 'open')>Aberto</option>
            <option value="paid" @selected($statusValue === 'paid')>Pago</option>
            <option value="cancelled" @selected($statusValue === 'cancelled')>Cancelado</option>
        </select>
        @error('status') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>Data de pagamento
        <input type="date" name="paid_at" value="{{ old('paid_at', isset($payable) && $payable->paid_at ? $payable->paid_at->format('Y-m-d') : '') }}">
        @error('paid_at') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>
<div class="field-grid">
    <label>Fornecedor
        <div style="display:grid; gap:8px;">
            @php
                $selectedSupplierId = (string) old('supplier_id', $payable->supplier_id ?? '');
                $selectedSupplier = $suppliers->firstWhere('id', (int) $selectedSupplierId);
            @endphp
            <input type="search" list="payable-suppliers-list" data-picker-input data-picker-target="#payable-supplier-id" value="{{ $selectedSupplier?->name }}" placeholder="Digite para buscar fornecedor" autocomplete="off">
            <input type="hidden" name="supplier_id" id="payable-supplier-id" value="{{ $selectedSupplierId }}">
            <datalist id="payable-suppliers-list">
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->name }}" data-value="{{ $supplier->id }}"></option>
                @endforeach
            </datalist>
            <span class="subtitle" style="font-size:12px;">Deixe em branco para conta sem fornecedor.</span>
        </div>
        @error('supplier_id') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>Categoria
        <select name="financial_category_id">
            <option value="">Sem categoria</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('financial_category_id', $payable->financial_category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('financial_category_id') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="field-grid">
    <label>Numero do documento
        <input name="document_number" value="{{ old('document_number', $payable->document_number ?? '') }}">
        @error('document_number') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>Linha digitavel
        <input name="digitable_line" value="{{ old('digitable_line', $payable->digitable_line ?? '') }}">
        @error('digitable_line') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

@if (! isset($payable) || ! $payable->exists)
    <section class="card" style="padding:14px;">
        <label style="display:flex; align-items:center; gap:8px;">
            <input type="checkbox" name="is_recurring" value="1" @checked(old('is_recurring')) style="width:auto; min-height:auto;" data-toggle-recurring>
            Criar recorrencia mensal
        </label>
        <div class="field-grid" style="margin-top:12px;" data-recurring-fields hidden>
            <label>Repetir ate
                <input type="month" name="recurrence_end_month" value="{{ old('recurrence_end_month') }}">
                @error('recurrence_end_month') <span class="error">{{ $message }}</span> @enderror
            </label>
            <div class="alert info" style="margin:0;">
                O sistema cria uma conta por mes mantendo o mesmo dia de vencimento, valor, fornecedor e categoria.
            </div>
        </div>
    </section>
@endif

<label>Observacoes
    <textarea name="notes">{{ old('notes', $payable->notes ?? '') }}</textarea>
    @error('notes') <span class="error">{{ $message }}</span> @enderror
</label>

<div class="actions">
    <button class="btn" type="submit">Salvar conta</button>
    <a class="btn secondary" href="{{ route('contas-a-pagar.index') }}">Cancelar</a>
</div>

<script>
    (() => {
        const checkbox = document.querySelector('[data-toggle-recurring]');
        const fields = document.querySelector('[data-recurring-fields]');
        if (!checkbox || !fields) return;
        const refresh = () => fields.hidden = !checkbox.checked;
        checkbox.addEventListener('change', refresh);
        refresh();
    })();
</script>
