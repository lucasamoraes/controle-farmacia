@extends('layouts.app', ['pageTitle' => 'Nova lista'])

@section('content')
    <div class="actions" style="justify-content:space-between;">
        <div>
            <h1 class="title">Nova lista de compras</h1>
            <p class="subtitle">Crie a lista e depois adicione os produtos em falta.</p>
        </div>
        <a class="btn secondary" href="{{ route('listas-compras.index') }}">Voltar</a>
    </div>

    <form class="form" method="post" action="{{ route('listas-compras.store') }}" style="margin-top:18px;">
        @csrf
        <label>Nome da lista
            <input name="title" value="{{ old('title', 'Lista '.now()->format('d/m/Y')) }}" required>
        </label>
        <label>Observacoes
            <textarea name="notes">{{ old('notes') }}</textarea>
        </label>
        <button class="btn" type="submit">Criar lista</button>
    </form>
@endsection
