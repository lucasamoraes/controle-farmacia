@extends('layouts.app', ['pageTitle' => 'Recibo do funcionario'])

@section('content')
    @php
        $netTotal = $earningsTotal - $deductionsTotal;
        $monthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $month.'-01')->translatedFormat('F \d\e Y');
        $canWriteFinance = auth()->user()->canWriteFinance($company);
    @endphp

    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Recibo do funcionario</h1>
            <p class="subtitle">Folha mensal de {{ $monthLabel }}.</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('funcionarios.index', ['mes' => $month]) }}">Voltar</a>
            @if ($canWriteFinance)
                <a class="btn secondary" href="{{ route('funcionarios.edit', $employee) }}">Editar funcionario</a>
            @endif
        </div>
    </div>

    <form class="filter-bar" method="get" action="{{ route('funcionarios.recibo', $employee) }}">
        <div class="filter-grid" style="grid-template-columns:minmax(180px, 240px) auto;">
            <label>Mes
                <input type="month" name="mes" value="{{ $month }}">
            </label>
            <button class="btn secondary" type="submit">Filtrar</button>
        </div>
    </form>

    <section class="card">
        <div class="actions" style="justify-content:space-between; align-items:flex-start;">
            <div>
                <h2 class="panel-title">{{ $company->name }}</h2>
                <p class="subtitle">
                    @if ($company->document)
                        CNPJ {{ $company->document }} ·
                    @endif
                    CC: Geral · Folha Mensal {{ $monthLabel }}
                </p>
            </div>
            <div style="text-align:right;">
                <div class="metric-label">Valor liquido</div>
                <div class="metric-value">R$ {{ number_format($netTotal, 2, ',', '.') }}</div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-top:18px;">
            <div style="border:1px solid var(--border); border-radius:8px; padding:14px;">
                <div class="metric-label">Total de vencimentos</div>
                <div class="metric-value">R$ {{ number_format($earningsTotal, 2, ',', '.') }}</div>
            </div>
            <div style="border:1px solid var(--border); border-radius:8px; padding:14px;">
                <div class="metric-label">Total de descontos</div>
                <div class="metric-value">R$ {{ number_format($deductionsTotal, 2, ',', '.') }}</div>
            </div>
            <div style="border:1px solid var(--border); border-radius:8px; padding:14px;">
                <div class="metric-label">Vales no mes</div>
                <div class="metric-value">R$ {{ number_format($advancesTotal, 2, ',', '.') }}</div>
            </div>
        </div>

        <div class="table-wrap" style="margin-top:18px;"><table>
            <tbody>
                <tr>
                    <th>Codigo</th><td>{{ $employee->employee_code ?? '-' }}</td>
                    <th>Nome</th><td><strong>{{ $employee->name }}</strong></td>
                </tr>
                <tr>
                    <th>Cargo</th><td>{{ $employee->role ?? '-' }}</td>
                    <th>CBO</th><td>{{ $employee->cbo_code ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Departamento</th><td>{{ $employee->department ?? '-' }}</td>
                    <th>Filial</th><td>{{ $employee->branch ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Admissao</th><td>{{ optional($employee->starts_on)->format('d/m/Y') ?? '-' }}</td>
                    <th>Pagamento</th><td>Dia {{ $employee->payment_day }}</td>
                </tr>
            </tbody>
        </table></div>
    </section>

    <div class="grid" style="grid-template-columns:1.5fr 1fr; margin-top:22px;">
        <section>
            <h2 class="panel-title">Eventos do recibo</h2>
            <div class="table-wrap"><table>
                <thead>
                    <tr><th>Codigo</th><th>Descricao</th><th>Referencia</th><th>Vencimentos</th><th>Descontos</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($payrollItems as $item)
                    <tr>
                        <td>{{ $item->code ?? '-' }}</td>
                        <td><strong>{{ $item->description }}</strong></td>
                        <td>{{ $item->reference ?? '-' }}</td>
                        <td>R$ {{ number_format($item->earning, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format($item->deduction, 2, ',', '.') }}</td>
                        <td>
                            @if ($canWriteFinance && empty($item->automatic))
                                <form method="post" action="{{ route('funcionarios.recibo.eventos.destroy', $item) }}" data-confirm-title="Excluir evento" data-confirm-message="Deseja remover este evento do recibo?" data-confirm-button="Excluir" data-confirm-danger="1">
                                    @csrf
                                    @method('delete')
                                    <button class="btn small danger" type="submit">Excluir</button>
                                </form>
                            @elseif (! empty($item->automatic))
                                <span class="status open">Automatico</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhum evento cadastrado para este mes.</td></tr>
                @endforelse
                </tbody>
            </table></div>

            @if ($canWriteFinance)
                <form class="form" method="post" action="{{ route('funcionarios.recibo.eventos.store', $employee) }}" style="margin-top:18px;">
                    @csrf
                    <input type="hidden" name="reference_month" value="{{ $month }}">
                    <h3 class="panel-title">Adicionar evento avulso</h3>
                    <p class="subtitle" style="margin-bottom:10px;">Use para premiacao, ferias, 13 salario, desconto manual ou ajuste pontual.</p>
                    <div class="field-grid">
                        <label>Codigo
                            <input name="code" value="{{ old('code') }}" placeholder="Ex: 1">
                            @error('code') <span class="error">{{ $message }}</span> @enderror
                        </label>
                        <label>Descricao
                            <input name="description" value="{{ old('description') }}" placeholder="Horas normais, INSS, gratificacao" required>
                            @error('description') <span class="error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <div class="field-grid">
                        <label>Referencia
                            <input name="reference" value="{{ old('reference') }}" placeholder="Ex: 220,00">
                            @error('reference') <span class="error">{{ $message }}</span> @enderror
                        </label>
                        <label>Vencimentos
                            <input type="number" step="0.01" min="0" name="earning" value="{{ old('earning', 0) }}">
                            @error('earning') <span class="error">{{ $message }}</span> @enderror
                        </label>
                        <label>Descontos
                            <input type="number" step="0.01" min="0" name="deduction" value="{{ old('deduction', 0) }}">
                            @error('deduction') <span class="error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <button class="btn" type="submit">Adicionar evento</button>
                </form>
            @endif
        </section>

        <section>
            <h2 class="panel-title">Vales descontados nesta folha</h2>
            <div class="table-wrap"><table>
                <thead>
                    <tr><th>Dia</th><th>Descricao</th><th>Forma</th><th>Valor</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($advances as $advance)
                    <tr>
                        <td>{{ $advance->advance_date->format('d/m/Y') }}</td>
                        <td><strong>{{ $advance->description }}</strong></td>
                        <td>{{ ucfirst($advance->payment_method) }}</td>
                        <td>R$ {{ number_format($advance->amount, 2, ',', '.') }}</td>
                        <td>
                            @if ($canWriteFinance)
                                <form method="post" action="{{ route('funcionarios.vales.destroy', $advance) }}" data-confirm-title="Excluir vale" data-confirm-message="Deseja remover este vale do mes?" data-confirm-button="Excluir" data-confirm-danger="1">
                                    @csrf
                                    @method('delete')
                                    <button class="btn small danger" type="submit">Excluir</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Nenhum vale cadastrado neste mes.</td></tr>
                @endforelse
                </tbody>
            </table></div>

            @if ($canWriteFinance)
                <form class="form" method="post" action="{{ route('funcionarios.vales.store', $employee) }}" style="margin-top:18px;">
                    @csrf
                    <h3 class="panel-title">Registrar vale</h3>
                    <label>Dia do vale
                        <input type="date" name="advance_date" value="{{ old('advance_date', $month.'-01') }}" required>
                        @error('advance_date') <span class="error">{{ $message }}</span> @enderror
                    </label>
                    <label>Descontar na folha
                        <input type="month" name="deduct_month" value="{{ old('deduct_month', \Illuminate\Support\Carbon::parse($month.'-01')->addMonthNoOverflow()->format('Y-m')) }}">
                        @error('deduct_month') <span class="error">{{ $message }}</span> @enderror
                    </label>
                    <label>Descricao
                        <input name="description" value="{{ old('description') }}" placeholder="Vale, adiantamento, ajuda de custo" required>
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </label>
                    <div class="field-grid">
                        <label>Valor
                            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required>
                            @error('amount') <span class="error">{{ $message }}</span> @enderror
                        </label>
                        <label>Forma
                            <select name="payment_method" required>
                                <option value="dinheiro" @selected(old('payment_method') === 'dinheiro')>Dinheiro</option>
                                <option value="pix" @selected(old('payment_method') === 'pix')>Pix</option>
                            </select>
                            @error('payment_method') <span class="error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <button class="btn" type="submit">Registrar vale</button>
                </form>
            @endif
        </section>
    </div>

    <section class="card" style="margin-top:22px;">
        <h2 class="panel-title">Bases de calculo</h2>
        <div class="table-wrap"><table>
            <thead>
                <tr><th>Salario base</th><th>Sal. Contr. INSS</th><th>Base Calc. FGTS</th><th>FGTS do mes</th><th>Base Calc. IRRF</th><th>Faixa IRRF</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>R$ {{ number_format($employee->base_salary, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($employee->inss_salary, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($employee->fgts_base, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($employee->fgts_month, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($employee->irrf_base, 2, ',', '.') }}</td>
                    <td>{{ number_format($employee->irrf_bracket, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table></div>
    </section>
@endsection
