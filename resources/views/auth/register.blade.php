@extends('layouts.app')

@section('content')
<div class="auth-shell">
    <form class="auth-box" method="post" action="{{ route('register.store') }}">
        @csrf
        <h1>Criar conta</h1>
        <p>Cadastre o primeiro usuario e a farmacia.</p>

        <label>Seu nome
            <input name="name" value="{{ old('name') }}" required autofocus>
            @error('name') <span class="error">{{ $message }}</span> @enderror
        </label>

        <label style="margin-top:14px;">E-mail
            <input type="email" name="email" value="{{ old('email') }}" required>
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="field-grid" style="margin-top:14px;">
            <label>Senha
                <input type="password" name="password" required>
                @error('password') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Confirmar senha
                <input type="password" name="password_confirmation" required>
            </label>
        </div>

        <label style="margin-top:14px;">Nome da farmacia
            <input name="company_name" value="{{ old('company_name') }}" required>
            @error('company_name') <span class="error">{{ $message }}</span> @enderror
        </label>

        <label style="margin-top:14px;">CNPJ da farmacia
            <input name="company_document" value="{{ old('company_document') }}">
            @error('company_document') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="actions" style="margin-top:20px; justify-content:space-between;">
            <a href="{{ route('login') }}">Ja tenho conta</a>
            <button class="btn" type="submit">Criar conta</button>
        </div>
    </form>
</div>
@endsection
