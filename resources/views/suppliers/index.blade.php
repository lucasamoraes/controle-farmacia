@extends('layouts.app', ['pageTitle' => 'Fornecedores'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Fornecedores</h1>
            <p class="subtitle">Base para boletos, despesas recorrentes e historico de pagamentos.</p>
        </div>
        <a class="btn" href="{{ route('fornecedores.create') }}">Novo fornecedor</a>
    </div>

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
                        <a class="btn small secondary" href="{{ route('fornecedores.edit', $supplier) }}">Editar</a>
                        @if ($supplier->is_active)
                            <form method="post" action="{{ route('fornecedores.destroy', $supplier) }}">
                                @csrf
                                @method('delete')
                                <button class="btn small danger" type="submit">Inativar</button>
                            </form>
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

