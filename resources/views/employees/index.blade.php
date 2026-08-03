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
            <div class="metric-label">Folha fixa ativa</div>
            <div class="metric-value">R$ {{ number_format($activeFixedPayrollTotal, 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="metric-label">Variavel previsto</div>
            <div class="metric-value">R$ {{ number_format($activeVariablePayrollTotal, 2, ',', '.') }}</div>
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
        <div class="card">
            <div class="actions" style="justify-content:space-between; align-items:end;">
                <form method="post" action="{{ route('funcionarios.generate-payables') }}" data-confirm-title="Gerar folha" data-confirm-message="Deseja gerar a folha consolidada deste mes? Se a folha ainda estiver aberta, o sistema atualiza os valores." data-confirm-button="Gerar folha">
                    @csrf
                    <label style="max-width:220px;">Mes
                        <input type="month" name="mes" value="{{ $month }}" required>
                    </label>
                    <button class="btn" type="submit" style="margin-top:10px;">Gerar folha do mes</button>
                </form>
                <div style="min-width:220px;">
                    <h2 class="panel-title" style="margin-bottom:4px;">Folha consolidada</h2>
                    <p class="subtitle">O fixo e o variavel entram como despesas do mes, sem criar uma conta por funcionario.</p>
                </div>
                <form method="post" action="{{ route('funcionarios.mark-payroll-paid') }}" data-confirm-title="Pagar folha" data-confirm-message="Deseja marcar a folha aberta deste mes como paga?" data-confirm-button="Pagar folha">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="mes" value="{{ $month }}">
                    <button class="btn secondary" type="submit">Pagar folha do mes</button>
                </form>
            </div>
        </div>
    @endif

    <div class="grid" style="grid-template-columns:1fr; margin-top:22px;">
        <section>
            <h2 class="panel-title">Equipe</h2>
            <div class="table-wrap"><table>
                <thead>
                    <tr><th>Nome</th><th>Cargo</th><th>Fixo</th><th>Variavel</th><th>Pagamento</th><th>Status</th><th></th></tr>
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
                        <td>R$ {{ number_format($employee->fixed_salary, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format($employee->variable_salary, 2, ',', '.') }}</td>
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
                    <tr><td colspan="7">Nenhum funcionario cadastrado.</td></tr>
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
