@extends('layouts.app', ['pageTitle' => 'Produtos'])

@php
    $fmtMoney = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $canManage = auth()->user()->canWriteFinance($company);
@endphp

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Produtos</h1>
            <p class="subtitle">Base de produtos usada nas listas de compras e cotacoes.</p>
        </div>
        @if ($canManage)
            <a class="btn secondary" href="{{ route('produtos.template') }}">Baixar modelo</a>
        @endif
    </div>

    <form class="filter-bar" method="get" action="{{ route('produtos.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(240px,1fr) auto;">
            <label>Buscar produto
                <input type="search" name="busca" value="{{ $search }}" placeholder="Descricao, EAN, grupo ou classe">
            </label>
            <div class="filter-actions">
                <button class="btn secondary" type="submit">Buscar</button>
                @if ($search !== '') <a class="btn secondary" href="{{ route('produtos.index') }}">Limpar</a> @endif
            </div>
        </div>
    </form>

    @if ($canManage)
        <details class="card" style="margin-top:18px;">
            <summary><strong>Adicionar novo produto</strong></summary>
            <form class="form" method="post" action="{{ route('produtos.store') }}" style="margin-top:14px;">
                @csrf
                <div class="field-grid">
                    <label>Descricao <input name="description" required></label>
                    <label>EAN <input name="ean"></label>
                    <label>Grupo <input name="group"></label>
                    <label>Classe <input name="class"></label>
                    <label>Ultimo valor de compra <input type="number" step="0.01" min="0" name="last_purchase_price" value="0"></label>
                    <label>URL da imagem <input type="url" name="image_url"></label>
                </div>
                <button class="btn" type="submit">Salvar produto</button>
            </form>
        </details>

        <section class="card" style="margin-top:18px;">
            <h2 class="panel-title">Importar produtos</h2>
            <form class="actions" method="post" action="{{ route('produtos.import') }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="planilha" required>
                <button class="btn" type="submit">Importar planilha</button>
            </form>
            <p class="subtitle" style="margin-top:10px;">Colunas aceitas: Descricao, EAN, Grupo, Classe, Ultimo Valor de Compra.</p>
        </section>
    @endif

    <section class="card" style="margin-top:18px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Produto</th><th>EAN</th><th>Grupo</th><th>Classe</th><th>Ultima compra</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>
                        <strong>{{ $product->description }}</strong>
                        @if ($product->image_url)
                            <br><a href="{{ $product->image_url }}" target="_blank" style="color:var(--brand);">ver imagem</a>
                        @endif
                    </td>
                    <td>{{ $product->ean ?: '-' }}</td>
                    <td>{{ $product->group ?: '-' }}</td>
                    <td>{{ $product->class ?: '-' }}</td>
                    <td>{{ $fmtMoney($product->last_purchase_price) }}</td>
                    <td><span class="status {{ $product->is_active ? 'paid' : 'cancelled' }}">{{ $product->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                    <td>
                        @if ($canManage)
                            <button class="btn small secondary" type="button" data-edit-toggle="product-{{ $product->id }}">Editar</button>
                            <form method="post" action="{{ route('produtos.destroy', $product) }}" style="display:inline;" data-confirm-message="Deseja alterar o status deste produto?" data-confirm-button="Confirmar">
                                @csrf @method('DELETE')
                                <button class="btn small danger" type="submit">{{ $product->is_active ? 'Inativar' : 'Ativar' }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @if ($canManage)
                    <tr hidden data-edit-field="product-{{ $product->id }}">
                        <td colspan="7">
                            <form class="form" method="post" action="{{ route('produtos.update', $product) }}" data-confirm-message="Deseja salvar as alteracoes deste produto?" data-confirm-button="Salvar">
                                @csrf @method('PUT')
                                <div class="field-grid">
                                    <label>Descricao <input name="description" value="{{ $product->description }}" required></label>
                                    <label>EAN <input name="ean" value="{{ $product->ean }}"></label>
                                    <label>Grupo <input name="group" value="{{ $product->group }}"></label>
                                    <label>Classe <input name="class" value="{{ $product->class }}"></label>
                                    <label>Ultimo valor <input type="number" step="0.01" min="0" name="last_purchase_price" value="{{ $product->last_purchase_price }}"></label>
                                    <label>URL da imagem <input type="url" name="image_url" value="{{ $product->image_url }}"></label>
                                </div>
                                <button class="btn" type="submit">Salvar</button>
                            </form>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="7">Nenhum produto cadastrado.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $products->links() }}
    </section>
@endsection
