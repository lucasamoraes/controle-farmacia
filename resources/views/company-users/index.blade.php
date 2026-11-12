@extends('layouts.app', ['pageTitle' => 'Usuarios'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Usuarios da farmacia</h1>
            <p class="subtitle">Controle quem acessa esta empresa e o que cada pessoa pode fazer.</p>
        </div>
    </div>

    <div class="grid" style="grid-template-columns:360px minmax(0, 1fr); align-items:start; margin-top:22px;">
        <form class="form" method="post" action="{{ route('usuarios.store') }}">
            @csrf
            <div>
                <h2 class="panel-title">Adicionar usuario</h2>
                <p class="subtitle">Use um e-mail existente para vincular, ou preencha nome e senha para criar um novo acesso.</p>
            </div>

            <label>Nome
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Nome do usuario">
            </label>

            <label>E-mail
                <input type="email" name="email" value="{{ old('email') }}" placeholder="usuario@email.com" required>
            </label>

            <label>Senha temporaria
                <input type="password" name="password" placeholder="Minimo 6 caracteres">
            </label>

            <label>Perfil
                <select name="role" required>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', 'finance') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <button class="btn" type="submit">Adicionar</button>
        </form>

        <section>
            <div class="table-wrap"><table>
                <thead><tr><th>Usuario</th><th>E-mail</th><th>Perfil</th><th></th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        @php $role = $user->pivot->role; @endphp
                        <tr>
                            <td><strong>{{ $user->name }}</strong></td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <form method="post" action="{{ route('usuarios.update', $user) }}" class="actions">
                                    @csrf
                                    @method('patch')
                                    <select name="role" style="min-width:150px;">
                                        @foreach ($roles as $value => $label)
                                            <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn small secondary" type="submit">Salvar</button>
                                </form>
                            </td>
                            <td class="actions">
                                <span class="role-pill">{{ $roles[$role] ?? 'Usuario' }}</span>
                                @if ($user->id !== auth()->id())
                                    <form method="post" action="{{ route('usuarios.destroy', $user) }}" data-confirm-title="Remover usuario" data-confirm-message="Deseja remover o acesso deste usuario a esta farmacia?" data-confirm-button="Remover" data-confirm-danger="1">
                                        @csrf
                                        @method('delete')
                                        <button class="btn small danger" type="submit">Remover</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>

            <div class="card" style="margin-top:16px;">
                <h2 class="panel-title">Perfis</h2>
                <p><strong>Dono:</strong> gerencia usuarios e todas as informacoes financeiras.</p>
                <p><strong>Financeiro:</strong> cria e edita fornecedores, boletos, contas e faturamento.</p>
                <p><strong>Consulta:</strong> visualiza dashboard, resumo e listas, sem alterar dados.</p>
            </div>
        </section>
    </div>

    <details class="card" style="margin-top:22px;">
        <summary style="cursor:pointer; font-weight:800;">Criar empresa demonstrativa FARMA TESTE</summary>
        <div style="margin-top:16px; max-width:760px;">
            <p class="subtitle">Cria uma farmacia separada com dados ficticios de janeiro a dezembro de 2026. O usuario tera perfil de dono apenas nessa empresa.</p>
            <form class="form" method="post" action="{{ route('usuarios.demo.store') }}" style="margin-top:16px;" data-confirm-title="Criar FARMA TESTE" data-confirm-message="Deseja criar a empresa demonstrativa completa? O processo pode levar alguns segundos." data-confirm-button="Criar ambiente">
                @csrf
                <div class="field-grid">
                    <label>Nome do visitante
                        <input type="text" name="demo_name" value="{{ old('demo_name', 'Usuario Demonstracao') }}" required>
                    </label>
                    <label>E-mail para acesso
                        <input type="email" name="demo_email" value="{{ old('demo_email', 'teste@farmateste.com.br') }}" required>
                    </label>
                    <label>Senha de demonstracao
                        <input type="password" name="demo_password" minlength="10" autocomplete="new-password" required>
                    </label>
                    <label>Confirmar senha
                        <input type="password" name="demo_password_confirmation" minlength="10" autocomplete="new-password" required>
                    </label>
                </div>
                <div class="alert info" style="margin:0;">
                    A senha nao fica gravada nesta tela. Compartilhe as credenciais somente com a empresa que fara o teste.
                </div>
                <button class="btn" type="submit">Criar ambiente demonstrativo</button>
            </form>
        </div>
    </details>
@endsection
