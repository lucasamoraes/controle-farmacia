@extends('layouts.app', ['pageTitle' => 'Funcionarios'])

@section('content')
    @php
        $canWriteFinance = auth()->user()->canWriteFinance($company);
        $statusLabels = ['active' => 'Ativos', 'inactive' => 'Inativos', '' => 'Todos'];
    @endphp

    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Funcionarios</h1>
            <p class="subtitle">Cadastro da equipe, recibos mensais, vales e eventos da folha.</p>
        </div>
        @if ($canWriteFinance)
            <div class="actions">
                <form method="post" action="{{ route('funcionarios.generate-payables') }}">
                    @csrf
                    <input type="hidden" name="mes" value="{{ $month }}">
                    <button class="btn secondary" type="submit">Atualizar previsao</button>
                </form>
                <a class="btn" href="{{ route('funcionarios.create') }}">Novo funcionario</a>
            </div>
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
            <div class="metric-label">Folha base ativa</div>
            <div class="metric-value">R$ {{ number_format($activeFixedPayrollTotal, 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="metric-label">Eventos avulsos</div>
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

    <div class="alert info">
        <strong>Folha automatica</strong>
        O salario base entra automaticamente no recibo de cada mes. Vales, premiacoes, ferias e 13 salario sao registrados no recibo do funcionario quando houver movimentacao.
    </div>

    <div class="grid" style="grid-template-columns:1fr; margin-top:22px;">
        <section>
            <h2 class="panel-title">Equipe</h2>
            <div class="table-wrap"><table>
                <thead>
                    <tr><th>Nome</th><th>Cargo</th><th>Admissao</th><th>Salario base</th><th>Pagamento</th><th>Status</th><th></th></tr>
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
                        <td>
                            {{ $employee->role ?? '-' }}
                            @if ($employee->cbo_code)
                                <br><span style="color:var(--muted);">CBO {{ $employee->cbo_code }}</span>
                            @endif
                        </td>
                        <td>{{ optional($employee->starts_on)->format('d/m/Y') ?? '-' }}</td>
                        <td>R$ {{ number_format($employee->base_salary, 2, ',', '.') }}</td>
                        <td>Dia {{ $employee->payment_day }}</td>
                        <td><span class="status {{ $employee->is_active ? 'paid' : 'cancelled' }}">{{ $employee->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                        <td class="actions">
                            @if ($canWriteFinance)
                                <a class="btn small secondary" href="{{ route('funcionarios.recibo', ['funcionario' => $employee, 'mes' => $month]) }}">Recibo</a>
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
    </div>
@endsection
