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
                <button class="btn" type="submit">Cadastrar cargo</button>
            </form>

            <div class="table-wrap" style="margin-top:18px;"><table>
                <thead><tr><th>Cargo</th><th>CBO</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($positions as $position)
                    <tr>
                        <td><strong>{{ $position->name }}</strong></td>
                        <td>{{ $position->cbo_code ?? '-' }}</td>
                        <td><span class="status {{ $position->is_active ? 'paid' : 'cancelled' }}">{{ $position->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                        <td>
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
                    <tr><td colspan="4">Nenhum cargo cadastrado.</td></tr>
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
                        <td><strong>{{ $department->name }}</strong></td>
                        <td><span class="status {{ $department->is_active ? 'paid' : 'cancelled' }}">{{ $department->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                        <td>
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
