@extends('layouts.app', ['pageTitle' => $invoice->exists ? 'Editar fatura' : 'Nova fatura'])

@section('content')
    @php
        $items = old('items', $invoice->exists ? $invoice->items->map(fn ($item) => [
            'description' => $item->description,
            'financial_category_id' => $item->financial_category_id,
            'amount' => $item->amount,
        ])->all() : [['description' => '', 'financial_category_id' => '', 'amount' => '']]);
    @endphp
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">{{ $invoice->exists ? 'Editar fatura' : 'Nova fatura' }}</h1>
            <p class="subtitle">O total da fatura vira uma conta a pagar; os itens alimentam a analise por categoria.</p>
        </div>
        <a class="btn secondary" href="{{ route('faturas-cartao.index') }}">Voltar</a>
    </div>

    <form class="form" method="post" action="{{ $invoice->exists ? route('faturas-cartao.update', $invoice) : route('faturas-cartao.store') }}" style="margin-top:22px; max-width:980px;">
        @csrf
        @if ($invoice->exists)
            @method('put')
        @endif

        <div class="field-grid">
            <label>Cartao
                <input name="card_name" value="{{ old('card_name', $invoice->card_name) }}" placeholder="Ex: Visa farmacia" required>
                @error('card_name') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Mes da fatura
                <input type="month" name="reference_month" value="{{ old('reference_month', optional($invoice->reference_month)->format('Y-m') ?? now()->format('Y-m')) }}" required>
                @error('reference_month') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>Vencimento
                <input type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
                @error('due_date') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Status
                @php $statusValue = old('status', $invoice->status ?: 'open'); @endphp
                <select name="status">
                    <option value="open" @selected($statusValue === 'open')>Aberta</option>
                    <option value="paid" @selected($statusValue === 'paid')>Paga</option>
                    <option value="cancelled" @selected($statusValue === 'cancelled')>Cancelada</option>
                </select>
                @error('status') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <label>Data de pagamento
            <input type="date" name="paid_at" value="{{ old('paid_at', optional($invoice->paid_at)->format('Y-m-d')) }}">
            @error('paid_at') <span class="error">{{ $message }}</span> @enderror
        </label>

        <section class="card" style="padding:14px;">
            <div class="actions" style="justify-content:space-between;">
                <h2 class="panel-title" style="margin:0;">Itens da fatura</h2>
                <button class="btn small secondary" type="button" data-add-card-item>Adicionar item</button>
            </div>
            <div data-card-items style="display:grid; gap:10px; margin-top:14px;">
                @foreach ($items as $index => $item)
                    <div class="field-grid" data-card-item style="grid-template-columns:2fr 1.4fr 1fr auto;">
                        <label>Descricao
                            <input name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" required>
                        </label>
                        <label>Categoria
                            <select name="items[{{ $index }}][financial_category_id]">
                                <option value="">Sem categoria</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) ($item['financial_category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Valor
                            <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][amount]" value="{{ $item['amount'] ?? '' }}" required data-card-amount>
                        </label>
                        <button class="btn small secondary" type="button" data-remove-card-item style="align-self:end;">Remover</button>
                    </div>
                @endforeach
            </div>
            <div class="metric-label" style="margin-top:14px;">Total da fatura</div>
            <div class="metric-value" data-card-total>R$ 0,00</div>
        </section>

        <label>Observacoes
            <textarea name="notes">{{ old('notes', $invoice->notes) }}</textarea>
            @error('notes') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="actions">
            <button class="btn" type="submit">Salvar fatura</button>
            <a class="btn secondary" href="{{ route('faturas-cartao.index') }}">Cancelar</a>
        </div>
    </form>

    <template data-card-item-template>
        <div class="field-grid" data-card-item style="grid-template-columns:2fr 1.4fr 1fr auto;">
            <label>Descricao
                <input data-name="description" required>
            </label>
            <label>Categoria
                <select data-name="financial_category_id">
                    <option value="">Sem categoria</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Valor
                <input type="number" step="0.01" min="0.01" data-name="amount" required data-card-amount>
            </label>
            <button class="btn small secondary" type="button" data-remove-card-item style="align-self:end;">Remover</button>
        </div>
    </template>

    <script>
        const itemsWrap = document.querySelector('[data-card-items]');
        const itemTemplate = document.querySelector('[data-card-item-template]');
        const totalEl = document.querySelector('[data-card-total]');
        const refreshCardItems = () => {
            const items = [...document.querySelectorAll('[data-card-item]')];
            items.forEach((item, index) => {
                item.querySelectorAll('[data-name]').forEach((field) => {
                    field.name = `items[${index}][${field.dataset.name}]`;
                });
            });
            const total = [...document.querySelectorAll('[data-card-amount]')]
                .reduce((sum, input) => sum + (Number(String(input.value).replace(',', '.')) || 0), 0);
            if (totalEl) totalEl.textContent = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        };
        document.querySelector('[data-add-card-item]')?.addEventListener('click', () => {
            const node = itemTemplate.content.firstElementChild.cloneNode(true);
            itemsWrap.appendChild(node);
            refreshCardItems();
        });
        document.addEventListener('click', (event) => {
            if (! event.target.matches('[data-remove-card-item]')) return;
            const rows = document.querySelectorAll('[data-card-item]');
            if (rows.length <= 1) return;
            event.target.closest('[data-card-item]')?.remove();
            refreshCardItems();
        });
        document.addEventListener('input', (event) => {
            if (event.target.matches('[data-card-amount]')) refreshCardItems();
        });
        refreshCardItems();
    </script>
@endsection
