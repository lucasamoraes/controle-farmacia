@extends('layouts.app', ['pageTitle' => 'Cadastros funcionarios'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Cadastros de funcionarios</h1>
            <p class="subtitle">Padronize cargos, CBO e departamentos usados na folha.</p>
        </div>
    </div>

    <details class="card" style="margin-top:22px;">
        <summary style="cursor:pointer; font-weight:800;">Adicionar novo cargo</summary>
        <form class="form" method="post" action="{{ route('configuracoes.funcionarios.cargos.store') }}" style="margin-top:14px;" data-confirm-title="Salvar cargo" data-confirm-message="Deseja cadastrar este cargo?" data-confirm-button="Salvar">
            @csrf
            <h2 class="panel-title">Novo cargo</h2>
            <label>Cargo
                <input name="name" required placeholder="Atendente de farmacia">
            </label>
            <label>CBO
                <input name="cbo_code" placeholder="521130">
            </label>
            <div class="field-grid">
                <label>Adicional
                    <select name="additional_type">
                        <option value="">Nenhum</option>
                        <option value="insalubridade">Insalubridade</option>
                        <option value="periculosidade">Periculosidade</option>
                    </select>
                </label>
                <label>Percentual
                    <input type="number" step="0.01" min="0" max="100" name="additional_percent" placeholder="Ex: 30">
                </label>
            </div>
            <button class="btn" type="submit">Cadastrar cargo</button>
        </form>
    </details>

    <details class="card" style="margin-top:12px;">
        <summary style="cursor:pointer; font-weight:800;">Adicionar novo departamento</summary>
        <form class="form" method="post" action="{{ route('configuracoes.funcionarios.departamentos.store') }}" style="margin-top:14px;" data-confirm-title="Salvar departamento" data-confirm-message="Deseja cadastrar este departamento?" data-confirm-button="Salvar">
            @csrf
            <h2 class="panel-title">Novo departamento</h2>
            <label>Departamento
                <input name="name" required placeholder="Balcao, administrativo">
            </label>
            <button class="btn" type="submit">Cadastrar departamento</button>
        </form>
    </details>

    <details class="card" style="margin-top:12px;">
        <summary style="cursor:pointer; font-weight:800;">Adicionar novo tipo de movimento</summary>
        <form class="form" method="post" action="{{ route('configuracoes.funcionarios.movimentos.store') }}" style="margin-top:14px;" data-confirm-title="Salvar movimento" data-confirm-message="Deseja cadastrar este tipo de movimento?" data-confirm-button="Salvar">
            @csrf
            <h2 class="panel-title">Novo tipo de movimento</h2>
            <label>Nome
                <input name="name" required placeholder="Ex: Bonificacao, vale, trabalho domingo">
            </label>
            <div class="field-grid">
                <label>Tipo financeiro
                    <select name="kind" required>
                        <option value="credit">Credito / acrescenta</option>
                        <option value="debit">Debito / desconta</option>
                    </select>
                </label>
                <label>Impostos
                    <select name="is_taxable">
                        <option value="1">Entra na base de impostos</option>
                        <option value="0">Nao entra na base de impostos</option>
                    </select>
                </label>
            </div>
            <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="requires_worked_date" value="1" style="width:auto; min-height:auto;">
                Pedir data trabalhada
            </label>
            <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="allows_paid_outside" value="1" style="width:auto; min-height:auto;">
                Permitir marcar como pago por fora
            </label>
            <button class="btn" type="submit">Cadastrar movimento</button>
        </form>
    </details>

    <form class="filter-bar" method="get" action="{{ route('configuracoes.funcionarios.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(240px, 1fr) auto;">
            <label>Buscar cadastro
                <input type="search" name="busca" value="{{ $search }}" placeholder="Cargo, CBO ou departamento">
            </label>
            <button class="btn secondary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-top:22px;">
        <section class="card">
            <h2 class="panel-title">Cargos</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Cargo</th><th>CBO</th><th>Adicional</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($positions as $position)
                    <tr>
                        <td>
                            <strong data-read-row="position-{{ $position->id }}">{{ $position->name }}</strong>
                            <form id="position-{{ $position->id }}" method="post" action="{{ route('configuracoes.funcionarios.cargos.update', $position) }}" hidden data-confirm-title="Salvar cargo" data-confirm-message="Deseja salvar as alteracoes deste cargo?" data-confirm-button="Salvar">
                                @csrf
                                @method('put')
                                <input name="name" value="{{ $position->name }}" required>
                            </form>
                        </td>
                        <td><span data-read-row="position-{{ $position->id }}">{{ $position->cbo_code ?? '-' }}</span><input name="cbo_code" value="{{ $position->cbo_code }}" form="position-{{ $position->id }}" hidden data-edit-field="position-{{ $position->id }}"></td>
                        <td>
                            <span data-read-row="position-{{ $position->id }}">{{ $position->additional_type ? ucfirst($position->additional_type).' '.$position->additional_percent.'%' : '-' }}</span>
                            <select name="additional_type" form="position-{{ $position->id }}" hidden data-edit-field="position-{{ $position->id }}">
                                <option value="">Sem adicional</option>
                                <option value="insalubridade" @selected($position->additional_type === 'insalubridade')>Insalubridade</option>
                                <option value="periculosidade" @selected($position->additional_type === 'periculosidade')>Periculosidade</option>
                            </select>
                            <input type="number" step="0.01" min="0" max="100" name="additional_percent" value="{{ $position->additional_percent }}" placeholder="%" form="position-{{ $position->id }}" hidden data-edit-field="position-{{ $position->id }}" style="margin-top:6px;">
                        </td>
                        <td><span class="status {{ $position->is_active ? 'paid' : 'cancelled' }}" data-read-row="position-{{ $position->id }}">{{ $position->is_active ? 'Ativo' : 'Inativo' }}</span><select name="is_active" form="position-{{ $position->id }}" hidden data-edit-field="position-{{ $position->id }}"><option value="1" @selected($position->is_active)>Ativo</option><option value="0" @selected(! $position->is_active)>Inativo</option></select></td>
                        <td class="actions">
                            <button class="btn small secondary" type="button" data-edit-toggle="position-{{ $position->id }}">Editar</button>
                            <button class="btn small" type="submit" form="position-{{ $position->id }}" hidden data-save-button="position-{{ $position->id }}">Salvar</button>
                            @if ($position->is_active)
                                <form method="post" action="{{ route('configuracoes.funcionarios.cargos.destroy', $position) }}" data-confirm-title="Inativar cargo" data-confirm-message="Deseja inativar este cargo?" data-confirm-button="Inativar" data-confirm-danger="1">
                                    @csrf
                                    @method('delete')
                                    <button class="btn small danger" type="submit">Inativar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Nenhum cargo cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>

        <section class="card">
            <h2 class="panel-title">Departamentos</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Departamento</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($departments as $department)
                    <tr>
                        <td>
                            <strong data-read-row="department-{{ $department->id }}">{{ $department->name }}</strong>
                            <form id="department-{{ $department->id }}" method="post" action="{{ route('configuracoes.funcionarios.departamentos.update', $department) }}" hidden data-confirm-title="Salvar departamento" data-confirm-message="Deseja salvar as alteracoes deste departamento?" data-confirm-button="Salvar">
                                @csrf
                                @method('put')
                                <input name="name" value="{{ $department->name }}" required>
                            </form>
                        </td>
                        <td><span class="status {{ $department->is_active ? 'paid' : 'cancelled' }}" data-read-row="department-{{ $department->id }}">{{ $department->is_active ? 'Ativo' : 'Inativo' }}</span><select name="is_active" form="department-{{ $department->id }}" hidden data-edit-field="department-{{ $department->id }}"><option value="1" @selected($department->is_active)>Ativo</option><option value="0" @selected(! $department->is_active)>Inativo</option></select></td>
                        <td class="actions">
                            <button class="btn small secondary" type="button" data-edit-toggle="department-{{ $department->id }}">Editar</button>
                            <button class="btn small" type="submit" form="department-{{ $department->id }}" hidden data-save-button="department-{{ $department->id }}">Salvar</button>
                            @if ($department->is_active)
                                <form method="post" action="{{ route('configuracoes.funcionarios.departamentos.destroy', $department) }}" data-confirm-title="Inativar departamento" data-confirm-message="Deseja inativar este departamento?" data-confirm-button="Inativar" data-confirm-danger="1">
                                    @csrf
                                    @method('delete')
                                    <button class="btn small danger" type="submit">Inativar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">Nenhum departamento cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>

    <section class="card" style="margin-top:22px;">
        <h2 class="panel-title">Tipos de movimento</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Movimento</th><th>Tipo</th><th>Regras</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($movementTypes as $movement)
                <tr>
                    <td>
                        <strong data-read-row="movement-{{ $movement->id }}">{{ $movement->name }}</strong>
                        <form id="movement-{{ $movement->id }}" method="post" action="{{ route('configuracoes.funcionarios.movimentos.update', $movement) }}" hidden data-confirm-title="Salvar movimento" data-confirm-message="Deseja salvar as alteracoes deste tipo de movimento?" data-confirm-button="Salvar">
                            @csrf
                            @method('put')
                            <input name="name" value="{{ $movement->name }}" required>
                        </form>
                    </td>
                    <td>
                        <span data-read-row="movement-{{ $movement->id }}">{{ $movement->kind === 'debit' ? 'Debito' : 'Credito' }}</span>
                        <select name="kind" form="movement-{{ $movement->id }}" hidden data-edit-field="movement-{{ $movement->id }}">
                            <option value="credit" @selected($movement->kind === 'credit')>Credito / acrescenta</option>
                            <option value="debit" @selected($movement->kind === 'debit')>Debito / desconta</option>
                        </select>
                    </td>
                    <td>
                        <span data-read-row="movement-{{ $movement->id }}">
                            {{ $movement->is_taxable ? 'Com impostos' : 'Sem impostos' }}
                            @if ($movement->requires_worked_date) · pede data @endif
                            @if ($movement->allows_paid_outside) · pago por fora @endif
                        </span>
                        <div hidden data-edit-field="movement-{{ $movement->id }}" style="display:grid; gap:6px;">
                            <select name="is_taxable" form="movement-{{ $movement->id }}">
                                <option value="1" @selected($movement->is_taxable)>Entra na base de impostos</option>
                                <option value="0" @selected(! $movement->is_taxable)>Nao entra na base de impostos</option>
                            </select>
                            <select name="requires_worked_date" form="movement-{{ $movement->id }}">
                                <option value="0" @selected(! $movement->requires_worked_date)>Nao pedir data trabalhada</option>
                                <option value="1" @selected($movement->requires_worked_date)>Pedir data trabalhada</option>
                            </select>
                            <select name="allows_paid_outside" form="movement-{{ $movement->id }}">
                                <option value="0" @selected(! $movement->allows_paid_outside)>Nao permitir pago por fora</option>
                                <option value="1" @selected($movement->allows_paid_outside)>Permitir pago por fora</option>
                            </select>
                        </div>
                    </td>
                    <td>
                        <span class="status {{ $movement->is_active ? 'paid' : 'cancelled' }}" data-read-row="movement-{{ $movement->id }}">{{ $movement->is_active ? 'Ativo' : 'Inativo' }}</span>
                        <select name="is_active" form="movement-{{ $movement->id }}" hidden data-edit-field="movement-{{ $movement->id }}">
                            <option value="1" @selected($movement->is_active)>Ativo</option>
                            <option value="0" @selected(! $movement->is_active)>Inativo</option>
                        </select>
                    </td>
                    <td class="actions">
                        <button class="btn small secondary" type="button" data-edit-toggle="movement-{{ $movement->id }}">Editar</button>
                        <button class="btn small" type="submit" form="movement-{{ $movement->id }}" hidden data-save-button="movement-{{ $movement->id }}">Salvar</button>
                        @if ($movement->is_active)
                            <form method="post" action="{{ route('configuracoes.funcionarios.movimentos.destroy', $movement) }}" data-confirm-title="Inativar movimento" data-confirm-message="Deseja inativar este tipo de movimento?" data-confirm-button="Inativar" data-confirm-danger="1">
                                @csrf
                                @method('delete')
                                <button class="btn small danger" type="submit">Inativar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Nenhum tipo de movimento cadastrado.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
@endsection
