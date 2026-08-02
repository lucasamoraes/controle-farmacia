@extends('layouts.app', ['pageTitle' => 'Fornecedores'])

@section('content')
    @php $canWriteFinance = auth()->user()->canWriteFinance($company); @endphp
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Fornecedores</h1>
            <p class="subtitle">Base para boletos, despesas recorrentes e historico de pagamentos.</p>
        </div>
        @if ($canWriteFinance)
            <a class="btn" href="{{ route('fornecedores.create') }}">Novo fornecedor</a>
        @endif
    </div>

    <form class="filter-bar" method="get" action="{{ route('fornecedores.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(220px, 1fr) auto;">
            <label>Buscar fornecedor
                <input type="search" name="busca" value="{{ $search }}" placeholder="Nome, fantasia, CNPJ, e-mail, categoria">
            </label>
            <div class="filter-actions">
                <button class="btn secondary" type="submit">Buscar</button>
                @if ($search !== '')
                    <a class="btn secondary" href="{{ route('fornecedores.index') }}">Limpar</a>
                @endif
            </div>
        </div>
    </form>

    <div style="margin-top:22px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Nome</th><th>CNPJ/CPF</th><th>Categoria</th><th>Contato</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($suppliers as $supplier)
                <tr>
                    <td><strong>{{ $supplier->name }}</strong><br><span style="color:var(--muted);">{{ $supplier->trade_name }}</span></td>
                    <td>{{ $supplier->document ?? '-' }}</td>
                    <td>{{ $supplier->category->name ?? '-' }}</td>
                    <td>{{ $supplier->email ?? '-' }}<br><span style="color:var(--muted);">{{ $supplier->phone }}</span></td>
                    <td><span class="status {{ $supplier->is_active ? 'open' : 'cancelled' }}">{{ $supplier->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                    <td class="actions">
                        @if ($canWriteFinance)
                        <a class="btn small secondary" href="{{ route('fornecedores.edit', $supplier) }}">Editar</a>
                        @if ($supplier->is_active)
                            <form method="post" action="{{ route('fornecedores.destroy', $supplier) }}" data-confirm-title="Inativar fornecedor" data-confirm-message="Deseja inativar este fornecedor? Ele não aparecerá nas novas contas, mas o histórico será mantido." data-confirm-button="Inativar" data-confirm-danger="1">
                                @csrf
                                @method('delete')
                                <button class="btn small danger" type="submit">Inativar</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('fornecedores.restore', $supplier) }}" data-confirm-title="Reativar fornecedor" data-confirm-message="Deseja reativar este fornecedor para novos boletos e contas?" data-confirm-button="Reativar">
                                @csrf
                                @method('patch')
                                <button class="btn small" type="submit">Reativar</button>
                            </form>
                        @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Nenhum fornecedor cadastrado.</td></tr>
            @endforelse
            </tbody>
        </table></div><div class="pagination">{{ $suppliers->links() }}</div>
    </div>
@endsection

