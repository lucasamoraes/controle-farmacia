@extends('layouts.app', ['pageTitle' => 'Cartoes'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Cartoes de credito</h1>
            <p class="subtitle">Cadastre os cartoes usados pela empresa para padronizar as faturas.</p>
        </div>
        <a class="btn secondary" href="{{ route('faturas-cartao.index') }}">Faturas</a>
    </div>

    <form class="filter-bar" method="get" action="{{ route('configuracoes.cartoes.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(240px, 1fr) auto;">
            <label>Buscar cartao
                <input type="search" name="busca" value="{{ $search }}" placeholder="Nome do cartao">
            </label>
            <button class="btn secondary" type="submit">Buscar</button>
        </div>
    </form>

    <details class="card" style="margin-top:22px;">
        <summary style="cursor:pointer; font-weight:800;">Adicionar novo cartao</summary>
    <form class="form" method="post" action="{{ route('configuracoes.cartoes.store') }}" style="margin-top:14px;" data-confirm-title="Salvar cartao" data-confirm-message="Deseja cadastrar este cartao?" data-confirm-button="Salvar">
        @csrf
        <h2 class="panel-title">Novo cartao</h2>
        <div class="field-grid">
            <label>Nome
                <input name="name" value="{{ old('name') }}" placeholder="Visa farmacia" required>
                @error('name') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Dia de vencimento
                <input type="number" name="due_day" min="1" max="31" value="{{ old('due_day', 10) }}" required>
                @error('due_day') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Dia de fechamento
                <input type="number" name="closing_day" min="1" max="31" value="{{ old('closing_day') }}">
                @error('closing_day') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>
        <button class="btn" type="submit">Cadastrar cartao</button>
    </form>
    </details>

    <div style="margin-top:22px;" class="table-wrap"><table>
        <thead><tr><th>Cartao</th><th>Fechamento</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($cards as $card)
            <tr>
                <td>
                    <strong data-read-row="card-{{ $card->id }}">{{ $card->name }}</strong>
                    <form id="card-{{ $card->id }}" method="post" action="{{ route('configuracoes.cartoes.update', $card) }}" hidden data-edit-form data-confirm-title="Salvar cartao" data-confirm-message="Deseja salvar as alteracoes deste cartao?" data-confirm-button="Salvar">
                        @csrf
                        @method('put')
                    </form>
                    <input name="name" value="{{ $card->name }}" required form="card-{{ $card->id }}" hidden data-edit-field="card-{{ $card->id }}">
                </td>
                <td><span data-read-row="card-{{ $card->id }}">{{ $card->closing_day ? 'Dia '.$card->closing_day : '-' }}</span><input type="number" name="closing_day" min="1" max="31" value="{{ $card->closing_day }}" placeholder="Fechamento" form="card-{{ $card->id }}" hidden data-edit-field="card-{{ $card->id }}"></td>
                <td><span data-read-row="card-{{ $card->id }}">Dia {{ $card->due_day }}</span><input type="number" name="due_day" min="1" max="31" value="{{ $card->due_day }}" required form="card-{{ $card->id }}" hidden data-edit-field="card-{{ $card->id }}"></td>
                <td><span class="status {{ $card->is_active ? 'paid' : 'cancelled' }}" data-read-row="card-{{ $card->id }}">{{ $card->is_active ? 'Ativo' : 'Inativo' }}</span><select name="is_active" form="card-{{ $card->id }}" hidden data-edit-field="card-{{ $card->id }}"><option value="1" @selected($card->is_active)>Ativo</option><option value="0" @selected(! $card->is_active)>Inativo</option></select></td>
                <td class="actions">
                    <button class="btn small secondary" type="button" data-edit-toggle="card-{{ $card->id }}">Editar</button>
                    <button class="btn small" type="submit" form="card-{{ $card->id }}" hidden data-save-button="card-{{ $card->id }}">Salvar</button>
                    @if ($card->is_active)
                        <form method="post" action="{{ route('configuracoes.cartoes.destroy', $card) }}" data-confirm-title="Inativar cartao" data-confirm-message="Deseja inativar este cartao para novas faturas?" data-confirm-button="Inativar">
                            @csrf
                            @method('delete')
                            <button class="btn small danger" type="submit">Inativar</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">Nenhum cartao cadastrado.</td></tr>
        @endforelse
        </tbody>
    </table></div>
@endsection
