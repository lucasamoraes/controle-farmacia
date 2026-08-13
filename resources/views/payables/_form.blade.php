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
            <div style="display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:8px;">
                <input type="search" data-supplier-search placeholder="Digite para filtrar fornecedor" autocomplete="off">
                <button class="btn secondary" type="button" data-supplier-search-button style="min-width:58px;">Lupa</button>
            </div>
            <select name="supplier_id" data-supplier-select>
            <option value="">Sem fornecedor</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" data-search="{{ mb_strtolower(($supplier->name ?? '') . ' ' . ($supplier->trade_name ?? '') . ' ' . ($supplier->document ?? '')) }}" @selected((string) old('supplier_id', $payable->supplier_id ?? '') === (string) $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
            </select>
            <span class="subtitle" data-supplier-search-count style="font-size:12px;"></span>
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
        const input = document.querySelector('[data-supplier-search]');
        const select = document.querySelector('[data-supplier-select]');
        const count = document.querySelector('[data-supplier-search-count]');
        const button = document.querySelector('[data-supplier-search-button]');
        if (!input || !select) return;

        const normalize = (value) => String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

        const filter = () => {
            const term = normalize(input.value);
            let visible = 0;

            [...select.options].forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const haystack = normalize(option.dataset.search || option.textContent);
                const match = term === '' || haystack.includes(term);
                option.hidden = !match;
                if (match) visible++;
            });

            const selected = select.options[select.selectedIndex];
            if (selected && selected.hidden) {
                select.value = '';
            }

            if (count) {
                count.textContent = term === ''
                    ? `${visible} fornecedor(es) disponivel(is)`
                    : `${visible} resultado(s) encontrado(s)`;
            }
        };

        input.addEventListener('input', filter);
        button?.addEventListener('click', () => {
            input.focus();
            filter();
        });
        filter();
    })();

    (() => {
        const checkbox = document.querySelector('[data-toggle-recurring]');
        const fields = document.querySelector('[data-recurring-fields]');
        if (!checkbox || !fields) return;
        const refresh = () => fields.hidden = !checkbox.checked;
        checkbox.addEventListener('change', refresh);
        refresh();
    })();
</script>
