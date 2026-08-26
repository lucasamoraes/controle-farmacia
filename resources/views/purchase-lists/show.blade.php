@extends('layouts.app', ['pageTitle' => 'Lista de compras'])

@php
    $statusLabels = ['open' => 'Aberta', 'quoting' => 'Em cotacao', 'finalized' => 'Finalizada'];
    $fmtMoney = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">{{ $list->title }}</h1>
            <p class="subtitle">Status: {{ $statusLabels[$list->status] ?? $list->status }} | Criada em {{ $list->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('listas-compras.index') }}">Voltar</a>
            @if ($canManageQuotation)
                <form method="post" action="{{ route('listas-compras.status.update', $list) }}" class="actions" data-confirm-message="Deseja alterar o status desta lista?" data-confirm-button="Alterar">
                    @csrf @method('PATCH')
                    <select name="status" style="width:170px;">
                        @foreach ($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected($list->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="btn secondary" type="submit">Alterar status</button>
                </form>
                @if ($list->items->count() > 0)
                    @if ($list->quotation)
                        <a class="btn" href="{{ route('cotacoes.show', $list->quotation) }}">Abrir cotacao</a>
                    @else
                        <form method="post" action="{{ route('cotacoes.start', $list) }}" data-confirm-message="Deseja iniciar a cotacao? O status ficara em cotacao, mas voce pode voltar para aberta se precisar ajustar." data-confirm-button="Iniciar">
                            @csrf
                            <button class="btn" type="submit">Iniciar cotacao</button>
                        </form>
                    @endif
                @endif
            @endif
        </div>
    </div>

    @if ($canEditItems)
        <section class="card" style="margin-top:18px;">
            <h2 class="panel-title">Adicionar produto cadastrado</h2>
            <form method="post" action="{{ route('listas-compras.itens.store', $list) }}" style="margin-top:16px;">
                @csrf
                <div class="filter-grid" style="grid-template-columns:minmax(280px,2fr) 120px 120px minmax(180px,1fr) auto;">
                    <label>Produto cadastrado
                        <input type="search" list="purchase-products-list" data-picker-input data-picker-target="#purchase-product-id" placeholder="Digite para buscar e selecione o produto" required autocomplete="off">
                        <input type="hidden" name="product_id" id="purchase-product-id">
                        <datalist id="purchase-products-list">
                            @foreach ($products as $product)
                                <option value="{{ $product->description }} | {{ $product->class ?: 'Sem classe' }} | {{ $fmtMoney($product->last_purchase_price) }}" data-value="{{ $product->id }}"></option>
                            @endforeach
                        </datalist>
                    </label>
                    <label>Qtd
                        <input type="number" step="1" min="1" name="quantity" value="1" required>
                    </label>
                    <label>Unidade
                        <input name="unit" value="un" required>
                    </label>
                    <label>Observacao
                        <input name="notes" placeholder="Opcional">
                    </label>
                    <div class="filter-actions"><button class="btn" type="submit">Adicionar</button></div>
                </div>
            </form>
        </section>

        <details class="card" style="margin-top:18px;">
            <summary><strong>Produto nao cadastrado? Cadastrar e adicionar na lista</strong></summary>
            <form method="post" action="{{ route('listas-compras.produtos.store', $list) }}" style="margin-top:14px;">
                @csrf
                <div class="filter-grid" style="grid-template-columns:minmax(260px,2fr) minmax(170px,1fr) 150px 100px 100px;">
                    <label>Descricao
                        <input name="description" required>
                    </label>
                    <label>Classe
                        <select name="class">
                            <option value="">Selecione</option>
                            @foreach ($productClasses as $class)
                                <option value="{{ $class->name }}">{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Ultima compra
                        <input type="number" step="0.01" min="0" name="last_purchase_price" value="0">
                    </label>
                    <label>Qtd
                        <input type="number" step="1" min="1" name="quantity" value="1" required>
                    </label>
                    <label>Unidade
                        <input name="unit" value="un" required>
                    </label>
                </div>
                <label style="margin-top:10px;">Observacao
                    <input name="notes" placeholder="Opcional">
                </label>
                <button class="btn" type="submit" style="margin-top:12px;">Cadastrar e adicionar</button>
            </form>
        </details>
    @elseif ($list->status !== 'open')
        <div class="alert info">Esta lista nao esta aberta. Se precisar ajustar, altere o status para Aberta.</div>
    @endif

    <section class="card" style="margin-top:18px;">
        <div class="actions" style="justify-content:space-between; align-items:flex-end;">
            <div>
                <h2 class="panel-title">Produtos da lista</h2>
                <p class="subtitle">Digite parte do nome, classe ou observacao para encontrar um item ja cadastrado nesta lista.</p>
            </div>
            <label style="max-width:420px; width:100%;">Buscar na lista
                <input type="search" data-list-filter="#purchase-list-items" placeholder="Ex: luftal, genericos, observacao">
            </label>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>Produto</th><th>Classe</th><th>Quantidade</th><th>Unidade</th><th>Ultima compra</th><th>Observacao</th><th></th></tr></thead>
            <tbody id="purchase-list-items">
            @forelse ($list->items as $item)
                @php
                    $productClass = $item->product?->class ?: '-';
                    $itemSearch = trim($item->description.' '.$productClass.' '.($item->notes ?: ''));
                @endphp
                <tr data-filter-row data-search="{{ $itemSearch }}">
                    <td data-sort-value="{{ $item->description }}"><strong>{{ $item->description }}</strong></td>
                    <td data-sort-value="{{ $productClass }}">{{ $productClass }}</td>
                    <td>
                        @if ($canEditItems)
                            <form method="post" action="{{ route('listas-compras.itens.update', $item) }}" class="actions" data-auto-submit>
                                @csrf @method('PUT')
                                <input type="number" step="1" min="1" name="quantity" value="{{ (float) $item->quantity }}" style="width:90px;" required data-auto-submit-input>
                                <input type="hidden" name="unit" value="{{ $item->unit }}">
                                <input type="hidden" name="last_purchase_price" value="{{ $item->product?->last_purchase_price ?? 0 }}">
                            </form>
                        @else
                            {{ number_format((float) $item->quantity, 3, ',', '.') }}
                        @endif
                    </td>
                    <td data-sort-value="{{ $item->unit }}">{{ $item->unit }}</td>
                    <td data-sort-value="{{ $item->product?->last_purchase_price ?? 0 }}">
                        @if ($canEditItems && $item->product)
                            <form method="post" action="{{ route('listas-compras.itens.update', $item) }}" data-auto-submit>
                                @csrf @method('PUT')
                                <input type="hidden" name="quantity" value="{{ (float) $item->quantity }}">
                                <input type="hidden" name="unit" value="{{ $item->unit }}">
                                <input type="number" step="0.01" min="0" name="last_purchase_price" value="{{ $item->product->last_purchase_price }}" style="width:120px;" data-auto-submit-input>
                            </form>
                        @else
                            {{ $fmtMoney($item->product?->last_purchase_price ?? 0) }}
                        @endif
                    </td>
                    <td data-sort-value="{{ $item->notes ?: '' }}">{{ $item->notes ?: '-' }}</td>
                    <td>
                        @if ($canEditItems)
                            <form method="post" action="{{ route('listas-compras.itens.destroy', $item) }}" data-confirm-message="Deseja remover este produto da lista?" data-confirm-button="Remover" data-confirm-danger="1">
                                @csrf @method('DELETE')
                                <button class="btn small danger" type="submit">Remover</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Nenhum produto adicionado.</td></tr>
            @endforelse
                <tr data-filter-empty style="display:none;"><td colspan="7">Nenhum produto encontrado nesta lista.</td></tr>
            </tbody>
        </table></div>
    </section>

    <script>
        document.querySelectorAll('[data-list-filter]').forEach((input) => {
            const target = document.querySelector(input.dataset.listFilter);
            if (!target) return;

            input.addEventListener('input', () => {
                const terms = input.value.toLocaleLowerCase('pt-BR').trim().split(/\s+/).filter(Boolean);
                let visibleRows = 0;

                target.querySelectorAll('[data-filter-row]').forEach((row) => {
                    const text = (row.dataset.search || row.innerText || '').toLocaleLowerCase('pt-BR');
                    const visible = terms.every((term) => text.includes(term));
                    row.style.display = visible ? '' : 'none';
                    if (visible) visibleRows++;
                });

                const empty = target.querySelector('[data-filter-empty]');
                if (empty) empty.style.display = visibleRows === 0 ? '' : 'none';
            });
        });
    </script>
@endsection
