@extends('layouts.app', ['pageTitle' => 'Configuracoes'])

@section('content')
    @php $canWriteFinance = auth()->user()->canWriteFinance($company); @endphp
    <div>
        <h1 class="title">Configuracoes</h1>
        <p class="subtitle">Gerencie categorias usadas em contas, fornecedores, faturas de cartao e dashboards.</p>
    </div>

    @if ($canWriteFinance)
        <details class="card" style="margin-top:22px;">
            <summary style="cursor:pointer; font-weight:800;">Adicionar nova categoria</summary>
        <form class="form" method="post" action="{{ route('configuracoes.categorias.store') }}" style="margin-top:14px;" data-confirm-title="Salvar categoria" data-confirm-message="Deseja cadastrar esta categoria?" data-confirm-button="Salvar">
            @csrf
            <h2 class="panel-title">Nova categoria</h2>
            <div class="field-grid">
                <label>Nome
                    <input name="name" value="{{ old('name') }}" required>
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </label>
                <label>Tipo
                    <select name="type">
                        <option value="expense">Despesa</option>
                        <option value="revenue">Receita</option>
                    </select>
                    @error('type') <span class="error">{{ $message }}</span> @enderror
                </label>
            </div>
            <div class="actions">
                <button class="btn" type="submit">Adicionar categoria</button>
            </div>
        </form>
        </details>
    @endif

    <form class="filter-bar" method="get" action="{{ route('configuracoes.categorias.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(240px, 1fr) auto;">
            <label>Buscar categoria
                <input type="search" name="busca" value="{{ $search }}" placeholder="Nome da categoria">
            </label>
            <button class="btn secondary" type="submit">Buscar</button>
        </div>
    </form>

    <section style="margin-top:22px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Nome</th><th>Tipo</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>
                        <strong data-read-row="category-{{ $category->id }}">{{ $category->name }}</strong>
                        @if ($canWriteFinance)
                            <form id="category-{{ $category->id }}" method="post" action="{{ route('configuracoes.categorias.update', $category) }}" hidden data-edit-form data-confirm-title="Salvar categoria" data-confirm-message="Deseja salvar as alteracoes desta categoria?" data-confirm-button="Salvar">
                                @csrf
                                @method('put')
                                <input name="name" value="{{ $category->name }}" required>
                            </form>
                        @endif
                    </td>
                    <td>
                        <span data-read-row="category-{{ $category->id }}">{{ $category->type === 'expense' ? 'Despesa' : 'Receita' }}</span>
                        @if ($canWriteFinance)
                            <select name="type" form="category-{{ $category->id }}" hidden data-edit-field="category-{{ $category->id }}">
                                <option value="expense" @selected($category->type === 'expense')>Despesa</option>
                                <option value="revenue" @selected($category->type === 'revenue')>Receita</option>
                            </select>
                        @endif
                    </td>
                    <td>
                        <span class="status {{ $category->is_active ? 'paid' : 'cancelled' }}" data-read-row="category-{{ $category->id }}">{{ $category->is_active ? 'Ativa' : 'Inativa' }}</span>
                        @if ($canWriteFinance)
                            <select name="is_active" form="category-{{ $category->id }}" hidden data-edit-field="category-{{ $category->id }}">
                                <option value="1" @selected($category->is_active)>Ativa</option>
                                <option value="0" @selected(! $category->is_active)>Inativa</option>
                            </select>
                        @endif
                    </td>
                    <td class="actions">
                        @if ($canWriteFinance)
                            <button class="btn small secondary" type="button" data-edit-toggle="category-{{ $category->id }}">Editar</button>
                            <button class="btn small" type="submit" form="category-{{ $category->id }}" hidden data-save-button="category-{{ $category->id }}">Salvar</button>
                            @if ($category->is_active)
                                <form method="post" action="{{ route('configuracoes.categorias.destroy', $category) }}" data-confirm-title="Inativar categoria" data-confirm-message="Deseja inativar esta categoria? O historico sera mantido." data-confirm-button="Inativar" data-confirm-danger="1">
                                    @csrf
                                    @method('delete')
                                    <button class="btn small danger" type="submit">Inativar</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Nenhuma categoria cadastrada.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="pagination">{{ $categories->links() }}</div>
    </section>
@endsection
