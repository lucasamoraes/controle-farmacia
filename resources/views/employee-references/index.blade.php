@extends('layouts.app', ['pageTitle' => 'Cadastros funcionarios'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Cadastros de funcionarios</h1>
            <p class="subtitle">Padronize cargos, CBO e departamentos usados na folha.</p>
        </div>
        <div class="actions">
            <button class="btn secondary" type="button" data-open-panel="new-position">Novo cargo</button>
            <button class="btn secondary" type="button" data-open-panel="new-department">Novo departamento</button>
        </div>
    </div>

    <form class="filter-bar" method="get" action="{{ route('configuracoes.funcionarios.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(240px, 1fr) auto;">
            <label>Buscar cadastro
                <input type="search" name="busca" value="{{ $search }}" placeholder="Cargo, CBO ou departamento">
            </label>
            <button class="btn secondary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-top:22px;">
        <form class="form" method="post" action="{{ route('configuracoes.funcionarios.cargos.store') }}" hidden data-panel="new-position" data-confirm-title="Salvar cargo" data-confirm-message="Deseja cadastrar este cargo?" data-confirm-button="Salvar">
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

        <form class="form" method="post" action="{{ route('configuracoes.funcionarios.departamentos.store') }}" hidden data-panel="new-department" data-confirm-title="Salvar departamento" data-confirm-message="Deseja cadastrar este departamento?" data-confirm-button="Salvar">
            @csrf
            <h2 class="panel-title">Novo departamento</h2>
            <label>Departamento
                <input name="name" required placeholder="Balcao, administrativo">
            </label>
            <button class="btn" type="submit">Cadastrar departamento</button>
        </form>
    </div>

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
@endsection
