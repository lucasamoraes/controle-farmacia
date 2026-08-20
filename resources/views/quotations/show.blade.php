@extends('layouts.app', ['pageTitle' => 'Cotacao'])

@php
    $fmtMoney = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $fmtPercent = fn ($value) => $value === null ? '-' : number_format((float) $value, 1, ',', '.') . '%';
@endphp

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Cotacao #{{ $quotation->id }}</h1>
            <p class="subtitle">Lista: {{ $list->title }} | Criada em {{ $quotation->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('listas-compras.show', $list) }}">Voltar</a>
            <a class="btn secondary" href="{{ route('cotacoes.export-list', $quotation) }}">Exportar lista Excel</a>
            @if ($quotation->status !== 'finalized')
                <form method="post" action="{{ route('cotacoes.finalize', $quotation) }}" data-confirm-message="Deseja finalizar esta cotacao? A lista sera encerrada." data-confirm-button="Finalizar">
                    @csrf @method('PATCH')
                    <button class="btn" type="submit">Finalizar cotacao</button>
                </form>
            @endif
        </div>
    </div>

    <section class="card" style="margin-top:18px;">
        <h2 class="panel-title">Fornecedores participantes</h2>
        <form class="actions" method="post" action="{{ route('cotacoes.fornecedores.store', $quotation) }}">
            @csrf
            <select name="supplier_id" required style="max-width:420px;">
                <option value="">Adicionar fornecedor de mercadoria</option>
                @foreach ($availableSuppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
            <button class="btn" type="submit">Adicionar</button>
        </form>

        <div class="actions" style="margin-top:12px;">
            @forelse ($suppliers as $supplier)
                <span class="status">{{ $supplier->name }}</span>
            @empty
                <p class="subtitle">Adicione os fornecedores que participarao da cotacao.</p>
            @endforelse
        </div>
    </section>

    @if ($suppliers->isNotEmpty())
        <section class="card" style="margin-top:18px;">
            <h2 class="panel-title">Importar precos por fornecedor</h2>
            <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                @foreach ($suppliers as $supplier)
                    <form method="post" action="{{ route('cotacoes.import-prices', [$quotation, $supplier]) }}" enctype="multipart/form-data" class="card" style="padding:14px;">
                        @csrf
                        <strong>{{ $supplier->name }}</strong>
                        <p class="subtitle" style="margin:6px 0 10px;">Planilha com colunas EAN/Descricao e Preco.</p>
                        <input type="file" name="planilha" required>
                        <button class="btn secondary" type="submit" style="margin-top:10px;">Importar precos</button>
                    </form>
                @endforeach
            </div>
        </section>
    @endif

    <section class="card" style="margin-top:18px;">
        <h2 class="panel-title">Mapa de cotacao</h2>
        <form method="post" action="{{ route('cotacoes.precos.update', $quotation) }}" data-confirm-message="Deseja salvar os precos desta cotacao?" data-confirm-button="Salvar">
            @csrf @method('PUT')
            <div class="table-wrap"><table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>EAN</th>
                        <th>Qtd</th>
                        <th>Ult. compra</th>
                        @foreach ($suppliers as $supplier)
                            <th>{{ $supplier->name }}</th>
                        @endforeach
                        <th>Vencedor</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($list->items as $item)
                    @php
                        $winner = $winners[$item->id] ?? null;
                        $lastPrice = (float) ($item->product?->last_purchase_price ?? 0);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $item->description }}</strong>
                            @if ($item->product?->image_url)
                                <br><a href="{{ $item->product->image_url }}" target="_blank" style="color:var(--brand);">imagem</a>
                            @endif
                        </td>
                        <td>{{ $item->ean ?: '-' }}</td>
                        <td>{{ number_format((float) $item->quantity, 3, ',', '.') }} {{ $item->unit }}</td>
                        <td>{{ $lastPrice > 0 ? $fmtMoney($lastPrice) : '-' }}</td>
                        @foreach ($suppliers as $supplier)
                            @php
                                $price = $matrix[$item->id][$supplier->id]->unit_price ?? null;
                                $isWinner = $winner && (int) $winner['supplier_id'] === (int) $supplier->id;
                            @endphp
                            <td style="{{ $isWinner ? 'background:#ecfdf5;' : '' }}">
                                <input type="number" step="0.01" min="0" name="prices[{{ $item->id }}][{{ $supplier->id }}]" value="{{ $price }}" style="width:120px;">
                                @if ($isWinner)
                                    <div class="status paid" style="margin-top:6px;">Menor preco</div>
                                @endif
                            </td>
                        @endforeach
                        <td>
                            @if ($winner)
                                @php $variation = $winner['variation']; @endphp
                                <strong>{{ $suppliers->firstWhere('id', $winner['supplier_id'])?->name }}</strong><br>
                                {{ $fmtMoney($winner['unit_price']) }}
                                @if ($variation !== null)
                                    <div style="color:{{ $variation > 0 ? 'var(--danger)' : 'var(--brand)' }}; font-weight:700;">
                                        {{ $variation > 0 ? '+' : '' }}{{ $fmtPercent($variation) }} vs ult. compra
                                    </div>
                                @endif
                            @else
                                <span style="color:var(--muted);">Sem preco</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 5 + $suppliers->count() }}">Nenhum produto na lista.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            @if ($suppliers->isNotEmpty())
                <div class="actions" style="justify-content:flex-end; margin-top:14px;">
                    <button class="btn" type="submit">Salvar precos</button>
                </div>
            @endif
        </form>
    </section>

    <section class="card" style="margin-top:18px;">
        <h2 class="panel-title">Pedidos por fornecedor vencedor</h2>
        <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));">
            @forelse ($suppliers as $supplier)
                <div class="card" style="padding:14px;">
                    <strong>{{ $supplier->name }}</strong>
                    <p class="subtitle" style="margin-top:6px;">Exporta apenas produtos vencidos por este fornecedor.</p>
                    <div class="actions" style="margin-top:10px;">
                        <a class="btn small secondary" href="{{ route('cotacoes.orders.export', [$quotation, $supplier]) }}">Excel</a>
                        <a class="btn small secondary" href="{{ route('cotacoes.orders.print', [$quotation, $supplier]) }}" target="_blank">PDF/Imprimir</a>
                    </div>
                </div>
            @empty
                <p class="subtitle">Adicione fornecedores e precos para gerar pedidos.</p>
            @endforelse
        </div>
    </section>
@endsection
