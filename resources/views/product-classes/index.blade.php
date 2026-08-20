@extends('layouts.app', ['pageTitle' => 'Classes de produtos'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Classes de produtos</h1>
            <p class="subtitle">Padronize as classes usadas no cadastro de produtos e nas cotacoes.</p>
        </div>
    </div>

    <form class="filter-bar" method="get" action="{{ route('configuracoes.classes-produtos.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(260px,1fr) auto;">
            <label>Buscar classe
                <input type="search" name="busca" value="{{ $search }}" placeholder="Nome da classe">
            </label>
            <div class="filter-actions">
                <button class="btn secondary" type="submit">Buscar</button>
                @if ($search !== '')
                    <a class="btn secondary" href="{{ route('configuracoes.classes-produtos.index') }}">Limpar</a>
                @endif
            </div>
        </div>
    </form>

    <details class="card" style="margin-top:18px;">
        <summary><strong>Adicionar nova classe</strong></summary>
        <form class="form" method="post" action="{{ route('configuracoes.classes-produtos.store') }}" style="margin-top:14px;">
            @csrf
            <label>Classe <input name="name" placeholder="Ex: GENERICOS" required></label>
            <button class="btn" type="submit">Cadastrar classe</button>
        </form>
    </details>

    <section class="card" style="margin-top:18px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Classe</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($classes as $class)
                <tr>
                    <td><strong>{{ $class->name }}</strong></td>
                    <td><span class="status {{ $class->is_active ? 'paid' : 'cancelled' }}">{{ $class->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                    <td>
                        <button class="btn small secondary" type="button" data-edit-toggle="product-class-{{ $class->id }}">Editar</button>
                        <form method="post" action="{{ route('configuracoes.classes-produtos.destroy', $class) }}" style="display:inline;" data-confirm-message="Deseja alterar o status desta classe?" data-confirm-button="Confirmar">
                            @csrf @method('DELETE')
                            <button class="btn small danger" type="submit">{{ $class->is_active ? 'Inativar' : 'Ativar' }}</button>
                        </form>
                    </td>
                </tr>
                <tr hidden data-edit-field="product-class-{{ $class->id }}">
                    <td colspan="3">
                        <form class="form" method="post" action="{{ route('configuracoes.classes-produtos.update', $class) }}" data-confirm-message="Deseja salvar as alteracoes desta classe?" data-confirm-button="Salvar">
                            @csrf @method('PUT')
                            <label>Classe <input name="name" value="{{ $class->name }}" required></label>
                            <button class="btn" type="submit">Salvar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3">Nenhuma classe cadastrada.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $classes->links() }}
    </section>
@endsection
