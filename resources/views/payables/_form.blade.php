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
        <select name="supplier_id">
            <option value="">Sem fornecedor</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $payable->supplier_id ?? '') === (string) $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
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

<label>Observacoes
    <textarea name="notes">{{ old('notes', $payable->notes ?? '') }}</textarea>
    @error('notes') <span class="error">{{ $message }}</span> @enderror
</label>

<div class="actions">
    <button class="btn" type="submit">Salvar conta</button>
    <a class="btn secondary" href="{{ route('contas-a-pagar.index') }}">Cancelar</a>
</div>

