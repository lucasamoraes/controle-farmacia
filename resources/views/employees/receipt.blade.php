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
                    <tr><th>Codigo</th><th>Descricao</th><th>Data</th><th>Pagamento</th><th>Vencimentos</th><th>Descontos</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($payrollItems as $item)
                    <tr>
                        <td>{{ $item->code ?? '-' }}</td>
                        <td><strong>{{ $item->description }}</strong></td>
                        <td>{{ ! empty($item->worked_date) ? $item->worked_date->format('d/m/Y') : '-' }}</td>
                        <td>
                            @if (! empty($item->paid_outside))
                                <span class="status paid">Pago por fora</span>
                            @elseif (in_array($item->event_type ?? '', ['sunday_work', 'holiday_work'], true))
                                <span class="status open">Na folha</span>
                            @else
                                -
                            @endif
                        </td>
                        <td>R$ {{ number_format($item->earning, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format($item->deduction, 2, ',', '.') }}</td>
                        <td>
                            @if ($canWriteFinance && empty($item->automatic))
                                <div class="actions">
                                    <button class="btn small secondary" type="button" data-edit-toggle="payroll-item-{{ $item->id }}">Editar</button>
                                    <form method="post" action="{{ route('funcionarios.recibo.eventos.destroy', $item) }}" data-confirm-title="Excluir evento" data-confirm-message="Deseja remover este evento do recibo?" data-confirm-button="Excluir" data-confirm-danger="1">
                                        @csrf
                                        @method('delete')
                                        <button class="btn small danger" type="submit">Excluir</button>
                                    </form>
                                </div>
                            @elseif (! empty($item->automatic))
                                <span class="status open">Automatico</span>
                            @endif
                        </td>
                    </tr>
                    @if ($canWriteFinance && empty($item->automatic))
                        @php
                            $selectedType = $movementTypes->firstWhere('code', $item->event_type) ?? $movementTypes->first();
                        @endphp
                        <tr hidden data-edit-field="payroll-item-{{ $item->id }}">
                            <td colspan="7">
                                <form class="form" method="post" action="{{ route('funcionarios.recibo.eventos.update', $item) }}" data-movement-form data-confirm-title="Salvar evento" data-confirm-message="Deseja salvar as alteracoes deste evento?" data-confirm-button="Salvar">
                                    @csrf
                                    @method('put')
                                    <input type="hidden" name="reference_month" value="{{ $month }}">
                                    <label>Tipo
                                        <select name="movement_type_id" required data-movement-type>
                                            @foreach ($movementTypes as $type)
                                                <option value="{{ $type->id }}"
                                                    data-code="{{ $type->code }}"
                                                    data-kind="{{ $type->kind }}"
                                                    data-worked-date="{{ $type->requires_worked_date ? '1' : '0' }}"
                                                    data-paid-outside="{{ $type->allows_paid_outside ? '1' : '0' }}"
                                                    @selected($selectedType && $selectedType->id === $type->id)
                                                >
                                                    {{ $type->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <div class="field-grid" data-work-fields>
                                        <label>Data trabalhada
                                            <input type="date" name="worked_date" value="{{ optional($item->worked_date)->format('Y-m-d') }}">
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; padding-top:24px;">
                                            <input type="checkbox" name="paid_outside" value="1" style="width:auto; min-height:auto;" @checked($item->paid_outside)>
                                            Ja foi pago por fora
                                        </label>
                                    </div>
                                    <div class="field-grid">
                                        <label>Codigo
                                            <input name="code" value="{{ $item->code }}">
                                        </label>
                                        <label>Descricao
                                            <input name="description" value="{{ $item->description }}" required>
                                        </label>
                                    </div>
                                    <div class="field-grid" style="grid-template-columns:1fr;">
                                        <label data-credit-field>Valor a acrescentar
                                            <input type="number" step="0.01" min="0" name="earning" value="{{ $item->earning }}">
                                        </label>
                                        <label data-debit-field>Valor a descontar
                                            <input type="number" step="0.01" min="0" name="deduction" value="{{ $item->deduction }}">
                                        </label>
                                    </div>
                                    <div class="actions">
                                        <button class="btn" type="submit">Salvar evento</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7">Nenhum evento cadastrado para este mes.</td></tr>
                @endforelse
                </tbody>
            </table></div>

            @if ($canWriteFinance)
                <form class="form" method="post" action="{{ route('funcionarios.recibo.eventos.store', $employee) }}" style="margin-top:18px;" data-movement-form>
                    @csrf
                    <input type="hidden" name="reference_month" value="{{ $month }}">
                    <h3 class="panel-title">Adicionar movimento</h3>
                    <p class="subtitle" style="margin-bottom:10px;">Use para vale, bonificacao, 13 salario, ferias, desconto ou outro movimento cadastrado.</p>
                    <label>Tipo
                        <select name="movement_type_id" required data-movement-type>
                            @foreach ($movementTypes as $type)
                                <option value="{{ $type->id }}"
                                    data-code="{{ $type->code }}"
                                    data-kind="{{ $type->kind }}"
                                    data-worked-date="{{ $type->requires_worked_date ? '1' : '0' }}"
                                    data-paid-outside="{{ $type->allows_paid_outside ? '1' : '0' }}"
                                >
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <div class="field-grid" data-work-fields hidden>
                        <label>Data trabalhada
                            <input type="date" name="worked_date" value="{{ old('worked_date') }}">
                            @error('worked_date') <span class="error">{{ $message }}</span> @enderror
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; padding-top:24px;">
                            <input type="checkbox" name="paid_outside" value="1" style="width:auto; min-height:auto;">
                            Ja foi pago por fora
                        </label>
                    </div>
                    <div class="alert info" style="margin:0;">
                        Trabalho domingo e feriado nao entram na base de impostos. Se ja foi pago, o sistema registra como despesa paga; se nao, entra na folha do mes.
                    </div>
                    <div class="field-grid">
                        <label>Codigo
                            <input name="code" value="{{ old('code') }}" placeholder="Ex: 1">
                            @error('code') <span class="error">{{ $message }}</span> @enderror
                        </label>
                        <label>Descricao
                            <input name="description" value="{{ old('description') }}" placeholder="Vale, bonificacao, ferias, 13 salario" required>
                            @error('description') <span class="error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <div class="field-grid" style="grid-template-columns:1fr;">
                        <label data-credit-field>Valor a acrescentar
                            <input type="number" step="0.01" min="0" name="earning" value="{{ old('earning', 0) }}">
                            @error('earning') <span class="error">{{ $message }}</span> @enderror
                        </label>
                        <label data-debit-field hidden>Valor a descontar
                            <input type="number" step="0.01" min="0" name="deduction" value="{{ old('deduction', 0) }}">
                            @error('deduction') <span class="error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <button class="btn" type="submit">Adicionar evento</button>
                </form>
            @endif
        </section>

        <section>
            <h2 class="panel-title">Vales antigos descontados nesta folha</h2>
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
    <script>
        document.querySelectorAll('[data-movement-form]').forEach((form) => {
            const movementSelect = form.querySelector('[data-movement-type]');
            const workFields = form.querySelector('[data-work-fields]');
            const creditField = form.querySelector('[data-credit-field]');
            const debitField = form.querySelector('[data-debit-field]');
            const earningInput = creditField?.querySelector('input');
            const deductionInput = debitField?.querySelector('input');
            const updateMovementFields = () => {
                const option = movementSelect?.selectedOptions?.[0];
                if (!option) return;
                const isDebit = option.dataset.kind === 'debit';
                const hasWorkDate = option.dataset.workedDate === '1';
                if (workFields) workFields.hidden = !hasWorkDate;
                if (creditField) creditField.hidden = isDebit;
                if (debitField) debitField.hidden = !isDebit;
                if (isDebit && earningInput) earningInput.value = '0';
                if (!isDebit && deductionInput) deductionInput.value = '0';
            };
            movementSelect?.addEventListener('change', updateMovementFields);
            updateMovementFields();
        });
    </script>
@endsection
