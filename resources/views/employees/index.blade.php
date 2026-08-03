@extends('layouts.app', ['pageTitle' => 'Funcionarios'])

@section('content')
    @php
        $canWriteFinance = auth()->user()->canWriteFinance($company);
        $statusLabels = ['active' => 'Ativos', 'inactive' => 'Inativos', '' => 'Todos'];
    @endphp

    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Funcionarios</h1>
            <p class="subtitle">Cadastro da equipe e geracao recorrente das despesas de salario.</p>
        </div>
        @if ($canWriteFinance)
            <a class="btn" href="{{ route('funcionarios.create') }}">Novo funcionario</a>
        @endif
    </div>

    <form class="filter-bar" method="get" action="{{ route('funcionarios.index') }}">
        <div class="filter-grid" style="grid-template-columns:2fr minmax(140px, 1fr) minmax(150px, 1fr) auto;">
            <label>Buscar funcionario
                <input type="search" name="busca" value="{{ $search }}" placeholder="Nome, CPF, cargo">
            </label>
            <label>Mes da folha
                <input type="month" name="mes" value="{{ $month }}">
            </label>
            <label>Status
                <select name="status">
                    <option value="">Todos</option>
                    <option value="active" @selected($statusFilter === 'active')>Ativos</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>Inativos</option>
                </select>
            </label>
            <button class="btn secondary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="grid stats">
        <div class="card">
            <div class="metric-label">Folha ativa</div>
            <div class="metric-value">R$ {{ number_format($activePayrollTotal, 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="metric-label">Despesas do mes</div>
            <div class="metric-value">R$ {{ number_format($monthExpenseTotal, 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="metric-label">Aberto no mes</div>
            <div class="metric-value">R$ {{ number_format($monthOpenTotal, 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="metric-label">Pago no mes</div>
            <div class="metric-value">R$ {{ number_format($monthPaidTotal, 2, ',', '.') }}</div>
        </div>
    </div>

    @if ($canWriteFinance)
        <form class="card" method="post" action="{{ route('funcionarios.generate-payables') }}" data-confirm-title="Gerar despesas" data-confirm-message="Deseja gerar as despesas de salario dos funcionarios ativos para este mes? O sistema vai ignorar despesas ja geradas." data-confirm-button="Gerar despesas">
            @csrf
            <div class="actions" style="justify-content:space-between; align-items:end;">
                <label style="max-width:220px;">Mes
                    <input type="month" name="mes" value="{{ $month }}" required>
                </label>
                <div>
                    <h2 class="panel-title" style="margin-bottom:4px;">Despesas recorrentes</h2>
                    <p class="subtitle">Gera contas a pagar na categoria Funcionarios, uma por funcionario ativo.</p>
                </div>
                <button class="btn" type="submit">Gerar despesas do mes</button>
            </div>
        </form>
    @endif

    <div class="grid" style="grid-template-columns:1fr; margin-top:22px;">
        <section>
            <h2 class="panel-title">Equipe</h2>
            <div class="table-wrap"><table>
                <thead>
                    <tr><th>Nome</th><th>Cargo</th><th>Salario</th><th>Pagamento</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td>
                            <strong>{{ $employee->name }}</strong>
                            @if ($employee->document)
                                <br><span style="color:var(--muted);">{{ $employee->document }}</span>
                            @endif
                        </td>
                        <td>{{ $employee->role ?? '-' }}</td>
                        <td>R$ {{ number_format($employee->salary, 2, ',', '.') }}</td>
                        <td>Dia {{ $employee->payment_day }}</td>
                        <td><span class="status {{ $employee->is_active ? 'paid' : 'cancelled' }}">{{ $employee->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                        <td class="actions">
                            @if ($canWriteFinance)
                                <a class="btn small secondary" href="{{ route('funcionarios.edit', $employee) }}">Editar</a>
                                @if ($employee->is_active)
                                    <form method="post" action="{{ route('funcionarios.destroy', $employee) }}" data-confirm-title="Inativar funcionario" data-confirm-message="Deseja inativar este funcionario? Ele nao entrara nas proximas despesas recorrentes." data-confirm-button="Inativar" data-confirm-danger="1">
                                        @csrf
                                        @method('delete')
                                        <button class="btn small danger" type="submit">Inativar</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('funcionarios.restore', $employee) }}" data-confirm-title="Reativar funcionario" data-confirm-message="Deseja reativar este funcionario para voltar a gerar despesas recorrentes?" data-confirm-button="Reativar">
                                        @csrf
                                        @method('patch')
                                        <button class="btn small" type="submit">Reativar</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhum funcionario cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="pagination">{{ $employees->links() }}</div>
        </section>

        <section>
            <h2 class="panel-title">Despesas de funcionarios no mes</h2>
            <div class="table-wrap"><table>
                <thead>
                    <tr><th>Vencimento</th><th>Descricao</th><th>Documento</th><th>Valor</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($monthExpenses as $expense)
                    @php $isOverdue = $expense->status === 'open' && $expense->due_date->isPast() && ! $expense->due_date->isToday(); @endphp
                    <tr>
                        <td>{{ $expense->due_date->format('d/m/Y') }}</td>
                        <td><strong>{{ $expense->description }}</strong></td>
                        <td>{{ $expense->document_number }}</td>
                        <td>R$ {{ number_format($expense->amount, 2, ',', '.') }}</td>
                        <td><span class="status {{ $isOverdue ? 'overdue' : $expense->status }}">{{ $isOverdue ? 'Vencido' : ['open' => 'Aberto', 'paid' => 'Pago', 'cancelled' => 'Cancelado'][$expense->status] ?? ucfirst($expense->status) }}</span></td>
                        <td>
                            @if ($canWriteFinance)
                                <a class="btn small secondary" href="{{ route('contas-a-pagar.edit', $expense) }}">Editar conta</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhuma despesa de funcionario para este mes.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
@endsection
