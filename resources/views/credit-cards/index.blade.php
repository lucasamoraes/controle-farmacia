@extends('layouts.app', ['pageTitle' => 'Cartoes'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Cartoes de credito</h1>
            <p class="subtitle">Cadastre os cartoes usados pela empresa para padronizar as faturas.</p>
        </div>
        <a class="btn secondary" href="{{ route('faturas-cartao.index') }}">Faturas</a>
    </div>

    <form class="form" method="post" action="{{ route('configuracoes.cartoes.store') }}" style="margin-top:22px;">
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

    <div style="margin-top:22px;" class="table-wrap"><table>
        <thead><tr><th>Cartao</th><th>Fechamento</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($cards as $card)
            <tr>
                <td colspan="5">
                    <form method="post" action="{{ route('configuracoes.cartoes.update', $card) }}" class="actions">
                        @csrf
                        @method('put')
                        <input name="name" value="{{ $card->name }}" required style="min-width:180px;">
                        <input type="number" name="closing_day" min="1" max="31" value="{{ $card->closing_day }}" placeholder="Fechamento" style="max-width:130px;">
                        <input type="number" name="due_day" min="1" max="31" value="{{ $card->due_day }}" required style="max-width:120px;">
                        <label style="display:flex; gap:6px; align-items:center; width:auto;">
                            <input type="checkbox" name="is_active" value="1" @checked($card->is_active) style="width:auto; min-height:auto;"> Ativo
                        </label>
                        <button class="btn small secondary" type="submit">Salvar</button>
                    </form>
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
