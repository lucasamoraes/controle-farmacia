@extends('layouts.app', ['pageTitle' => 'Lista de compras'])

@php
    $statusLabels = ['open' => 'Aberta', 'quoting' => 'Em cotacao', 'finalized' => 'Finalizada'];
@endphp

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">{{ $list->title }}</h1>
            <p class="subtitle">Status: {{ $statusLabels[$list->status] ?? $list->status }} | Criada em {{ $list->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('listas-compras.index') }}">Voltar</a>
            @if ($canManageQuotation && $list->items->count() > 0)
                @if ($list->quotation)
                    <a class="btn" href="{{ route('cotacoes.show', $list->quotation) }}">Abrir cotacao</a>
                @else
                    <form method="post" action="{{ route('cotacoes.start', $list) }}" data-confirm-message="Deseja iniciar a cotacao? A lista deixara de aceitar novos itens." data-confirm-button="Iniciar">
                        @csrf
                        <button class="btn" type="submit">Iniciar cotacao</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    @if ($canEditItems)
        <section class="card" style="margin-top:18px;">
            <h2 class="panel-title">Adicionar produto</h2>
            <form method="post" action="{{ route('listas-compras.itens.store', $list) }}">
                @csrf
                <div class="filter-grid" style="grid-template-columns:2fr 1fr 100px 1fr auto;">
                    <label>Produto cadastrado
                        <select name="product_id">
                            <option value="">Selecionar ou digitar abaixo</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->description }} @if($product->ean)- {{ $product->ean }}@endif</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Descricao livre
                        <input name="description" placeholder="Se nao estiver cadastrado">
                    </label>
                    <label>Qtd
                        <input type="number" step="0.001" min="0.001" name="quantity" value="1" required>
                    </label>
                    <label>Unidade
                        <input name="unit" value="un" required>
                    </label>
                    <div class="filter-actions"><button class="btn" type="submit">Adicionar</button></div>
                </div>
                <label style="margin-top:10px;">Observacao
                    <input name="notes" placeholder="Opcional">
                </label>
            </form>
        </section>
    @elseif ($list->status !== 'open')
        <div class="alert info">Esta lista ja entrou em cotacao ou foi finalizada. Para adicionar novos produtos, crie uma nova lista.</div>
    @endif

    <section class="card" style="margin-top:18px;">
        <h2 class="panel-title">Produtos da lista</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Produto</th><th>EAN</th><th>Quantidade</th><th>Unidade</th><th>Observacao</th><th></th></tr></thead>
            <tbody>
            @forelse ($list->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->description }}</strong>
                        @if ($item->product?->image_url)
                            <br><a href="{{ $item->product->image_url }}" target="_blank" style="color:var(--brand);">ver imagem</a>
                        @endif
                    </td>
                    <td>{{ $item->ean ?: '-' }}</td>
                    <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                    <td>{{ $item->unit }}</td>
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
                <tr><td colspan="6">Nenhum produto adicionado.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
@endsection
