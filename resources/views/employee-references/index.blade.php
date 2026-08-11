@extends('layouts.app', ['pageTitle' => 'Cadastros funcionarios'])

@section('content')
    <div>
        <h1 class="title">Cadastros de funcionarios</h1>
        <p class="subtitle">Padronize cargos, CBO e departamentos usados na folha.</p>
    </div>

    <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-top:22px;">
        <section>
            <form class="form" method="post" action="{{ route('configuracoes.funcionarios.cargos.store') }}">
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

            <div class="table-wrap" style="margin-top:18px;"><table>
                <thead><tr><th>Cargo</th><th>CBO</th><th>Adicional</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($positions as $position)
                    <tr>
                        <td colspan="5">
                            <form method="post" action="{{ route('configuracoes.funcionarios.cargos.update', $position) }}" class="actions">
                                @csrf
                                @method('put')
                                <input name="name" value="{{ $position->name }}" required style="min-width:180px;">
                                <input name="cbo_code" value="{{ $position->cbo_code }}" placeholder="CBO" style="max-width:110px;">
                                <select name="additional_type" style="max-width:170px;">
                                    <option value="">Sem adicional</option>
                                    <option value="insalubridade" @selected($position->additional_type === 'insalubridade')>Insalubridade</option>
                                    <option value="periculosidade" @selected($position->additional_type === 'periculosidade')>Periculosidade</option>
                                </select>
                                <input type="number" step="0.01" min="0" max="100" name="additional_percent" value="{{ $position->additional_percent }}" placeholder="%" style="max-width:90px;">
                                <label style="display:flex; gap:6px; align-items:center; width:auto;">
                                    <input type="checkbox" name="is_active" value="1" @checked($position->is_active) style="width:auto; min-height:auto;"> Ativo
                                </label>
                                <button class="btn small secondary" type="submit">Salvar</button>
                            </form>
                            @if ($position->is_active)
                                <form method="post" action="{{ route('configuracoes.funcionarios.cargos.destroy', $position) }}">
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

        <section>
            <form class="form" method="post" action="{{ route('configuracoes.funcionarios.departamentos.store') }}">
                @csrf
                <h2 class="panel-title">Novo departamento</h2>
                <label>Departamento
                    <input name="name" required placeholder="Balcao, administrativo">
                </label>
                <button class="btn" type="submit">Cadastrar departamento</button>
            </form>

            <div class="table-wrap" style="margin-top:18px;"><table>
                <thead><tr><th>Departamento</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($departments as $department)
                    <tr>
                        <td colspan="3">
                            <form method="post" action="{{ route('configuracoes.funcionarios.departamentos.update', $department) }}" class="actions">
                                @csrf
                                @method('put')
                                <input name="name" value="{{ $department->name }}" required style="min-width:180px;">
                                <label style="display:flex; gap:6px; align-items:center; width:auto;">
                                    <input type="checkbox" name="is_active" value="1" @checked($department->is_active) style="width:auto; min-height:auto;"> Ativo
                                </label>
                                <button class="btn small secondary" type="submit">Salvar</button>
                            </form>
                            @if ($department->is_active)
                                <form method="post" action="{{ route('configuracoes.funcionarios.departamentos.destroy', $department) }}">
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
