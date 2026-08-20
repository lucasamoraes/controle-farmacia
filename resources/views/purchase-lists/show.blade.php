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
        <h2 class="panel-title">Produtos da lista</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Produto</th><th>Classe</th><th>Quantidade</th><th>Unidade</th><th>Ultima compra</th><th>Observacao</th><th></th></tr></thead>
            <tbody>
            @forelse ($list->items as $item)
                <tr>
                    <td><strong>{{ $item->description }}</strong></td>
                    <td>{{ $item->product?->class ?: '-' }}</td>
                    <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ $fmtMoney($item->product?->last_purchase_price ?? 0) }}</td>
                    <td>{{ $item->notes ?: '-' }}</td>
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
            </tbody>
        </table></div>
    </section>
@endsection
