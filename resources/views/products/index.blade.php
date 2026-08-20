@extends('layouts.app', ['pageTitle' => 'Produtos'])

@php
    $fmtMoney = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $canManage = auth()->user()->canWriteFinance($company);
@endphp

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Produtos</h1>
            <p class="subtitle">Cadastro simples para cotacao: descricao, classe e ultimo valor de compra.</p>
        </div>
    </div>

    <form class="filter-bar" method="get" action="{{ route('produtos.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(260px,1fr) auto;">
            <label>Buscar produto
                <input type="search" name="busca" value="{{ $search }}" placeholder="Descricao do produto">
            </label>
            <div class="filter-actions">
                <button class="btn secondary" type="submit">Buscar</button>
                @if ($search !== '' || $selectedClasses !== [])
                    <a class="btn secondary" href="{{ route('produtos.index') }}">Limpar</a>
                @endif
            </div>
        </div>
        <div class="quick-filters" style="margin-top:10px;">
            @foreach ($productClasses as $class)
                <label class="quick-filter" style="cursor:pointer;">
                    <input type="checkbox" name="classes[]" value="{{ $class->name }}" @checked(in_array($class->name, $selectedClasses, true)) style="width:auto; min-height:0;">
                    {{ $class->name }}
                </label>
            @endforeach
        </div>
    </form>

    @if ($canManage)
        <details class="card" style="margin-top:18px;">
            <summary><strong>Adicionar novo produto</strong></summary>
            <form class="form" method="post" action="{{ route('produtos.store') }}" style="margin-top:14px;">
                @csrf
                <div class="field-grid">
                    <label>Descricao <input name="description" required></label>
                    <label>Classe
                        <select name="class">
                            <option value="">Selecione</option>
                            @foreach ($productClasses as $class)
                                <option value="{{ $class->name }}">{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Ultimo valor de compra <input type="number" step="0.01" min="0" name="last_purchase_price" value="0"></label>
                </div>
                <button class="btn" type="submit">Salvar produto</button>
            </form>
        </details>
    @endif

    <section class="card" style="margin-top:18px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Produto</th><th>Classe</th><th>Ultima compra</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($products as $product)
                <tr>
                    <td><strong>{{ $product->description }}</strong></td>
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
                        <td colspan="5">
                            <form class="form" method="post" action="{{ route('produtos.update', $product) }}" data-confirm-message="Deseja salvar as alteracoes deste produto?" data-confirm-button="Salvar">
                                @csrf @method('PUT')
                                <div class="field-grid">
                                    <label>Descricao <input name="description" value="{{ $product->description }}" required></label>
                                    <label>Classe
                                        <select name="class">
                                            <option value="">Selecione</option>
                                            @foreach ($productClasses as $class)
                                                <option value="{{ $class->name }}" @selected($product->class === $class->name)>{{ $class->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Ultimo valor <input type="number" step="0.01" min="0" name="last_purchase_price" value="{{ $product->last_purchase_price }}"></label>
                                </div>
                                <button class="btn" type="submit">Salvar</button>
                            </form>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="5">Nenhum produto cadastrado.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $products->links() }}
    </section>
@endsection
