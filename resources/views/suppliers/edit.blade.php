@extends('layouts.app', ['pageTitle' => 'Editar fornecedor'])

@section('content')
    <h1 class="title">Editar fornecedor</h1>
    <p class="subtitle" style="margin-bottom:22px;">Atualize dados cadastrais e categoria padrao.</p>

    <form class="form" method="post" action="{{ route('fornecedores.update', $supplier) }}">
        @csrf
        @method('put')
        @include('suppliers._form')
    </form>
@endsection
