@extends('layouts.app', ['pageTitle' => $invoice->exists ? 'Editar fatura' : 'Nova fatura'])

@section('content')
    @php
        $items = old('items', $invoice->exists ? $invoice->items->map(fn ($item) => [
            'description' => $item->description,
            'financial_category_id' => $item->financial_category_id,
            'amount' => $item->amount,
            'is_recurring' => $item->is_recurring,
            'recurrence_start_month' => optional($item->recurrence_start_month)->format('Y-m'),
            'recurrence_end_month' => optional($item->recurrence_end_month)->format('Y-m'),
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
                <select name="credit_card_id" required>
                    <option value="">Selecione</option>
                    @foreach ($creditCards as $card)
                        <option value="{{ $card->id }}" @selected((string) old('credit_card_id', $invoice->credit_card_id) === (string) $card->id)>{{ $card->name }}</option>
                    @endforeach
                </select>
                @error('credit_card_id') <span class="error">{{ $message }}</span> @enderror
                @if ($creditCards->isEmpty())
                    <span class="error">Cadastre um cartao em Configuracao > Cartoes antes de lancar faturas.</span>
                @endif
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
                    <div data-card-item class="card" style="padding:12px;">
                        <div class="field-grid" style="grid-template-columns:2fr 1.4fr 1fr auto;">
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
                        <div class="field-grid" style="grid-template-columns:160px repeat(2, minmax(120px, 1fr)); margin-top:10px;">
                            <label style="display:flex; align-items:center; gap:8px; padding-top:20px;">
                                <input type="checkbox" name="items[{{ $index }}][is_recurring]" value="1" @checked(! empty($item['is_recurring'])) style="width:auto; min-height:auto;">
                                Recorrente
                            </label>
                            <label>Inicio
                                <input type="month" name="items[{{ $index }}][recurrence_start_month]" value="{{ $item['recurrence_start_month'] ?? '' }}">
                            </label>
                            <label>Fim
                                <input type="month" name="items[{{ $index }}][recurrence_end_month]" value="{{ $item['recurrence_end_month'] ?? '' }}">
                            </label>
                        </div>
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
        <div data-card-item class="card" style="padding:12px;">
            <div class="field-grid" style="grid-template-columns:2fr 1.4fr 1fr auto;">
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
            <div class="field-grid" style="grid-template-columns:160px repeat(2, minmax(120px, 1fr)); margin-top:10px;">
                <label style="display:flex; align-items:center; gap:8px; padding-top:20px;">
                    <input type="checkbox" data-name="is_recurring" value="1" style="width:auto; min-height:auto;">
                    Recorrente
                </label>
                <label>Inicio
                    <input type="month" data-name="recurrence_start_month">
                </label>
                <label>Fim
                    <input type="month" data-name="recurrence_end_month">
                </label>
            </div>
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
