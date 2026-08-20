@extends('layouts.app', ['pageTitle' => 'Listas de compras'])

@php
    $labels = ['open' => 'Aberta', 'quoting' => 'Em cotacao', 'finalized' => 'Finalizada'];
@endphp

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Listas de compras</h1>
            <p class="subtitle">Produtos em falta cadastrados pela equipe antes da cotacao.</p>
        </div>
        @if (auth()->user()->canWritePurchaseList($company))
            <a class="btn" href="{{ route('listas-compras.create') }}">Nova lista</a>
        @endif
    </div>

    <form class="filter-bar" method="get" action="{{ route('listas-compras.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(220px,1fr) 180px auto;">
            <label>Buscar
                <input type="search" name="busca" value="{{ $search }}" placeholder="Nome da lista">
            </label>
            <label>Status
                <select name="status">
                    <option value="">Todos</option>
                    @foreach ($labels as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="filter-actions"><button class="btn secondary" type="submit">Filtrar</button></div>
        </div>
    </form>

    <section class="card" style="margin-top:18px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Lista</th><th>Status</th><th>Itens</th><th>Criada por</th><th>Data</th><th></th></tr></thead>
            <tbody>
            @forelse ($lists as $list)
                <tr>
                    <td><strong>{{ $list->title }}</strong><br><span style="color:var(--muted);">{{ $list->notes }}</span></td>
                    <td><span class="status {{ $list->status === 'open' ? 'open' : ($list->status === 'finalized' ? 'paid' : '') }}">{{ $labels[$list->status] ?? $list->status }}</span></td>
                    <td>{{ $list->items_count }}</td>
                    <td>{{ $list->creator?->name ?: '-' }}</td>
                    <td>{{ $list->created_at->format('d/m/Y') }}</td>
                    <td><a class="btn small secondary" href="{{ route('listas-compras.show', $list) }}">Abrir</a></td>
                </tr>
            @empty
                <tr><td colspan="6">Nenhuma lista cadastrada.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $lists->links() }}
    </section>
@endsection
