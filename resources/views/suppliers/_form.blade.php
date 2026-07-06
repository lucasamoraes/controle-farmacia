<div class="field-grid">
    <label>Razao social / nome
        <input name="name" value="{{ old('name', $supplier->name ?? '') }}" required>
        @error('name') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>Nome fantasia
        <input name="trade_name" value="{{ old('trade_name', $supplier->trade_name ?? '') }}">
        @error('trade_name') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="field-grid">
    <label>CNPJ / CPF
        <input name="document" value="{{ old('document', $supplier->document ?? '') }}">
        @error('document') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>Categoria padrao
        <select name="financial_category_id">
            <option value="">Sem categoria</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('financial_category_id', $supplier->financial_category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('financial_category_id') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="field-grid">
    <label>E-mail
        <input type="email" name="email" value="{{ old('email', $supplier->email ?? '') }}">
        @error('email') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>Telefone
        <input name="phone" value="{{ old('phone', $supplier->phone ?? '') }}">
        @error('phone') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="field-grid">
    <label>Situacao cadastral
        <input name="legal_status" value="{{ old('legal_status', $supplier->legal_status ?? '') }}">
        @error('legal_status') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>CNAE principal
        <input name="main_activity" value="{{ old('main_activity', $supplier->main_activity ?? '') }}">
        @error('main_activity') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="field-grid">
    <label>Logradouro
        <input name="street" value="{{ old('street', $supplier->street ?? '') }}">
        @error('street') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>Numero
        <input name="number" value="{{ old('number', $supplier->number ?? '') }}">
        @error('number') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="field-grid">
    <label>Bairro
        <input name="district" value="{{ old('district', $supplier->district ?? '') }}">
        @error('district') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>CEP
        <input name="zip_code" value="{{ old('zip_code', $supplier->zip_code ?? '') }}">
        @error('zip_code') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="field-grid">
    <label>Cidade
        <input name="city" value="{{ old('city', $supplier->city ?? '') }}">
        @error('city') <span class="error">{{ $message }}</span> @enderror
    </label>
    <label>UF
        <input name="state" maxlength="2" value="{{ old('state', $supplier->state ?? '') }}">
        @error('state') <span class="error">{{ $message }}</span> @enderror
    </label>
</div>

<label>Observacoes
    <textarea name="notes">{{ old('notes', $supplier->notes ?? '') }}</textarea>
    @error('notes') <span class="error">{{ $message }}</span> @enderror
</label>

<div class="actions">
    <button class="btn" type="submit">Salvar fornecedor</button>
    <a class="btn secondary" href="{{ route('fornecedores.index') }}">Cancelar</a>
</div>
