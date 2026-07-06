@extends('layouts.app')

@section('content')
<div class="auth-shell">
    <form class="auth-box" method="post" action="{{ route('login.store') }}">
        @csrf
        <h1>Entrar</h1>
        <p>Acesse o controle financeiro da farmacia.</p>

        <label>E-mail
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </label>

        <label style="margin-top:14px;">Senha
            <input type="password" name="password" required>
            @error('password') <span class="error">{{ $message }}</span> @enderror
        </label>

        <label style="display:flex; align-items:center; gap:8px; margin-top:14px; font-weight:400;">
            <input type="checkbox" name="remember" value="1" style="width:auto;"> Lembrar acesso
        </label>

        <div class="actions" style="margin-top:20px; justify-content:space-between;">
            <a href="{{ route('register') }}">Criar conta</a>
            <button class="btn" type="submit">Entrar</button>
        </div>
    </form>
</div>
@endsection
