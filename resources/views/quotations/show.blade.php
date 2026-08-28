@extends('layouts.app', ['pageTitle' => 'Cotacao'])

@php
    $fmtMoney = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $fmtPercent = fn ($value) => $value === null ? '-' : number_format((float) $value, 1, ',', '.') . '%';
@endphp

<style>
    .quote-toolbar { display:flex; justify-content:space-between; gap:12px; align-items:end; flex-wrap:wrap; margin:12px 0; }
    .quote-toolbar label { max-width:180px; }
    .quote-scroll-top { overflow-x:auto; overflow-y:hidden; height:16px; border:1px solid var(--line); border-radius:8px; background:#fff; margin:10px 0 8px; }
    .quote-scroll-top-inner { height:1px; }
    .quote-table-wrap { max-height:72vh; overflow:auto; position:relative; }
    .quote-table { min-width:980px; table-layout:fixed; }
    .quote-table th, .quote-table td { min-width:124px; width:124px; background:var(--panel); padding:10px; }
    .quote-table thead th { position:sticky; top:0; z-index:4; background:#eef3f8; }
    .quote-table .sticky-product { position:sticky; left:0; z-index:3; min-width:280px; width:280px; box-shadow:1px 0 0 var(--line); }
    .quote-table .sticky-qty { position:sticky; left:280px; z-index:3; min-width:112px; width:112px; box-shadow:1px 0 0 var(--line); }
    .quote-table thead .sticky-product, .quote-table thead .sticky-qty { z-index:6; background:#eef3f8; }
    .quote-table .quote-last-price { min-width:96px; width:96px; }
    .quote-table .quote-supplier-col { min-width:128px; width:128px; }
    .quote-table .quote-winner-col { min-width:150px; width:150px; }
    .quote-product-name { display:block; line-height:1.25; overflow-wrap:anywhere; }
    .quote-product-cell { display:grid; grid-template-columns:1fr auto; gap:8px; align-items:start; }
    .quote-remove-item { width:26px; height:26px; min-height:26px; padding:0; border-radius:999px; font-size:16px; line-height:1; flex:none; }
    .quote-table input[type="number"] { min-height:38px; padding:8px; }
    .quote-price-input { width:100% !important; min-width:0; }
    .quote-supplier-menu { position:relative; }
    .quote-supplier-menu summary { cursor:pointer; list-style:none; display:flex; align-items:center; justify-content:space-between; gap:8px; min-height:32px; border:1px solid transparent; border-radius:6px; padding:4px 6px; color:#243b53; }
    .quote-supplier-menu summary::-webkit-details-marker { display:none; }
    .quote-supplier-menu summary::after { content:'\22EE'; color:#64748b; font-size:16px; line-height:1; }
    .quote-supplier-name { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:96px; }
    .quote-supplier-menu[open] summary { background:#e8eef5; border-color:#cbd5e1; }
    .quote-supplier-actions { position:absolute; top:38px; left:0; z-index:20; min-width:136px; background:#fff; border:1px solid var(--line); border-radius:8px; padding:8px; box-shadow:0 14px 32px rgba(15,23,42,.14); display:grid; gap:6px; }
    .quote-supplier-actions .btn { width:100%; }
    .quote-table tbody tr[hidden] { display:none; }
    .quote-map-search { min-width:260px; max-width:420px; flex:1; }
    .quote-map-info { margin-top:12px; color:var(--muted); font-size:13px; }
</style>

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
        <form class="filter-grid" method="post" action="{{ route('cotacoes.fornecedores.store', $quotation) }}" style="grid-template-columns:minmax(260px,1.2fr) minmax(220px,1fr) auto;">
            @csrf
            <label>Fornecedor cadastrado
                <input type="search" list="quotation-suppliers-list" data-picker-input data-picker-target="#quotation-supplier-id" placeholder="Digite para buscar fornecedor de mercadoria" required autocomplete="off">
                <input type="hidden" name="supplier_id" id="quotation-supplier-id">
                <datalist id="quotation-suppliers-list">
                @foreach ($availableSuppliers as $supplier)
                    <option value="{{ $supplier->name }}" data-value="{{ $supplier->id }}"></option>
                @endforeach
                </datalist>
            </label>
            <label>Nome nesta cotacao
                <input name="display_name" placeholder="Ex: Panpharma - Joao">
            </label>
            <div class="filter-actions"><button class="btn" type="submit">Adicionar</button></div>
        </form>

        <div class="actions" style="margin-top:12px;">
            @forelse ($participants as $participant)
                <form method="post" action="{{ route('cotacoes.fornecedores.destroy', [$quotation, $participant]) }}" class="actions" data-confirm-message="Deseja remover {{ $participant->quoted_name }} desta cotacao? Os precos lancados para este participante tambem serao removidos." data-confirm-button="Remover" data-confirm-danger="1">
                    @csrf @method('DELETE')
                    <span class="status">{{ $participant->quoted_name }}</span>
                    <button class="btn small danger" type="submit">Remover</button>
                </form>
            @empty
                <p class="subtitle">Adicione os fornecedores que participarao da cotacao.</p>
            @endforelse
        </div>
    </section>

    @if ($participants->isNotEmpty())
        <section class="card" style="margin-top:18px;">
            <h2 class="panel-title">Importar precos por fornecedor</h2>
            <p class="subtitle" style="margin-bottom:12px;">Selecione o fornecedor que enviou a planilha. Colunas esperadas: Descricao e Preco.</p>
            <form method="post" action="" enctype="multipart/form-data" class="filter-grid" style="grid-template-columns:minmax(260px,1.4fr) minmax(240px,1fr) auto;" data-dynamic-import-form>
                @csrf
                <label>Fornecedor
                    <input type="search" list="quotation-import-suppliers-list" data-picker-input data-picker-target="#quotation-import-supplier-url" placeholder="Digite para buscar fornecedor participante" required autocomplete="off">
                    <input type="hidden" id="quotation-import-supplier-url" data-url-target>
                    <datalist id="quotation-import-suppliers-list">
                        @foreach ($participants as $participant)
                            <option value="{{ $participant->quoted_name }}" data-value="{{ route('cotacoes.import-prices', [$quotation, $participant]) }}"></option>
                        @endforeach
                    </datalist>
                </label>
                <label>Planilha de precos
                    <input type="file" name="planilha" required>
                </label>
                <div class="filter-actions">
                    <button class="btn secondary" type="submit">Importar precos</button>
                </div>
            </form>
            <div class="actions" style="margin-top:12px;">
                @foreach ($participants as $participant)
                    <span class="status">{{ $participant->quoted_name }}</span>
                @endforeach
            </div>
        </section>
    @endif

    <section class="card" style="margin-top:18px;">
        <div class="quote-toolbar">
            <div>
                <h2 class="panel-title">Mapa de cotacao</h2>
                <p class="subtitle">Use a rolagem horizontal; produto, quantidade e cabecalho ficam fixos para facilitar cotacoes grandes.</p>
            </div>
            <label class="quote-map-search">Buscar produto no mapa
                <input type="search" data-quote-search placeholder="Digite parte do nome do produto" autocomplete="off">
            </label>
        </div>
        <form method="post" action="{{ route('cotacoes.precos.update', $quotation) }}" data-confirm-message="Deseja salvar os precos desta cotacao?" data-confirm-button="Salvar">
            @csrf @method('PUT')
            <div class="quote-scroll-top" data-quote-scroll-top><div class="quote-scroll-top-inner" data-quote-scroll-top-inner></div></div>
            <div class="table-wrap quote-table-wrap" data-quote-table-wrap><table class="quote-table">
                <thead>
                    <tr>
                        <th class="sticky-product">Produto</th>
                        <th class="sticky-qty">Qtd</th>
                        <th class="quote-last-price">Ult. compra</th>
                        @foreach ($participants as $participant)
                            <th class="quote-supplier-col">
                                <details class="quote-supplier-menu">
                                    <summary title="{{ $participant->quoted_name }}">
                                        <span class="quote-supplier-name">{{ $participant->quoted_name }}</span>
                                    </summary>
                                    <div class="quote-supplier-actions">
                                        <a class="btn small secondary" href="{{ route('cotacoes.orders.export', [$quotation, $participant]) }}">Excel</a>
                                        <a class="btn small secondary" href="{{ route('cotacoes.orders.print', [$quotation, $participant]) }}" target="_blank">PDF</a>
                                    </div>
                                </details>
                            </th>
                        @endforeach
                        <th class="quote-winner-col">Vencedor</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($list->items as $item)
                    @php
                        $winner = $winners[$item->id] ?? null;
                        $lastPrice = (float) ($item->product?->last_purchase_price ?? 0);
                    @endphp
                    <tr data-quote-row data-quote-search-text="{{ mb_strtolower($item->description) }}">
                        <td class="sticky-product">
                            <div class="quote-product-cell">
                                <div>
                                    <strong class="quote-product-name">{{ $item->description }}</strong>
                                    @if ($item->product?->image_url)
                                        <a href="{{ $item->product->image_url }}" target="_blank" style="color:var(--brand); font-size:12px;">imagem</a>
                                    @endif
                                </div>
                                @if ($quotation->status !== 'finalized')
                                    <button class="btn small danger quote-remove-item" type="submit" form="quote-remove-item-{{ $item->id }}" title="Remover produto da cotacao" aria-label="Remover {{ $item->description }}">&times;</button>
                                @endif
                            </div>
                        </td>
                        <td class="sticky-qty">
                            <div style="display:grid; gap:4px; width:86px;">
                                <input type="number" step="1" min="0" name="quantities[{{ $item->id }}]" value="{{ (float) $item->quantity }}" title="Use 0 para remover este produto da cotacao" style="width:86px;">
                                <span style="color:var(--muted); font-size:12px;">{{ $item->unit }} | 0 remove</span>
                            </div>
                        </td>
                        <td class="quote-last-price">{{ $lastPrice > 0 ? $fmtMoney($lastPrice) : '-' }}</td>
                        @foreach ($participants as $participant)
                            @php
                                $price = $matrix[$item->id][$participant->id]->unit_price ?? null;
                                $isLowest = $winner && (int) ($winner['lowest_quotation_supplier_id'] ?? 0) === (int) $participant->id;
                                $isWinner = $winner && (int) ($winner['quotation_supplier_id'] ?? 0) === (int) $participant->id;
                            @endphp
                            <td class="quote-supplier-col" style="{{ $isWinner ? 'background:#ecfdf5;' : '' }}">
                                <input class="quote-price-input" type="number" step="0.01" min="0" name="prices[{{ $item->id }}][{{ $participant->id }}]" value="{{ $price }}">
                                @if ($price)
                                    <label style="display:flex; align-items:center; gap:6px; margin-top:7px; font-size:12px; font-weight:700;">
                                        <input type="radio" name="selected_winners[{{ $item->id }}]" value="{{ $participant->id }}" @checked($isWinner) style="width:auto; min-height:0;">
                                        Escolher
                                    </label>
                                @endif
                                @if ($isLowest && ! $isWinner)
                                    <div class="status" style="margin-top:6px;">Menor preco</div>
                                @endif
                                @if ($isWinner)
                                    <div class="status paid" style="margin-top:6px;">Selecionado</div>
                                @endif
                            </td>
                        @endforeach
                        <td class="quote-winner-col">
                            @if ($winner)
                                @php $variation = $winner['variation']; @endphp
                                <strong>{{ $participants->firstWhere('id', $winner['quotation_supplier_id'])?->quoted_name }}</strong><br>
                                {{ $fmtMoney($winner['unit_price']) }}
                                @if ($winner['manual'] && (int) $winner['quotation_supplier_id'] !== (int) ($winner['lowest_quotation_supplier_id'] ?? 0))
                                    <div class="status" style="margin-top:6px;">Escolha manual</div>
                                    <div style="color:var(--muted); font-size:12px; margin-top:4px;">Menor: {{ $fmtMoney($winner['lowest_unit_price']) }}</div>
                                @endif
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
                    <tr><td colspan="{{ 4 + $participants->count() }}">Nenhum produto na lista.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="quote-map-info" data-quote-map-info></div>
            @if ($participants->isNotEmpty())
                <div class="actions" style="justify-content:flex-end; margin-top:14px;">
                    <button class="btn" type="submit">Salvar precos</button>
                </div>
            @endif
        </form>
        @if ($quotation->status !== 'finalized')
            @foreach ($list->items as $item)
                <form id="quote-remove-item-{{ $item->id }}" method="post" action="{{ route('cotacoes.itens.destroy', [$quotation, $item]) }}" data-confirm-message="Deseja remover {{ $item->description }} desta cotacao? Os precos lancados para este produto tambem serao removidos." data-confirm-button="Remover" data-confirm-danger="1">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        @endif
    </section>

    <script>
        (() => {
            const tableWrap = document.querySelector('[data-quote-table-wrap]');
            const topScroll = document.querySelector('[data-quote-scroll-top]');
            const topInner = document.querySelector('[data-quote-scroll-top-inner]');
            const rows = [...document.querySelectorAll('[data-quote-row]')];
            const search = document.querySelector('[data-quote-search]');
            const mapInfo = document.querySelector('[data-quote-map-info]');

            const syncWidth = () => {
                if (tableWrap && topInner) topInner.style.width = `${tableWrap.scrollWidth}px`;
            };

            if (tableWrap && topScroll) {
                syncWidth();
                window.addEventListener('resize', syncWidth);
                topScroll.addEventListener('scroll', () => { tableWrap.scrollLeft = topScroll.scrollLeft; });
                tableWrap.addEventListener('scroll', () => { topScroll.scrollLeft = tableWrap.scrollLeft; });
            }

            document.querySelectorAll('.quote-supplier-menu').forEach((menu) => {
                menu.addEventListener('toggle', () => {
                    if (!menu.open) return;
                    document.querySelectorAll('.quote-supplier-menu[open]').forEach((other) => {
                        if (other !== menu) other.removeAttribute('open');
                    });
                });
            });

            const normalize = (value) => value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');

            const filterRows = () => {
                const term = normalize(search?.value || '');
                let visible = 0;

                rows.forEach((row) => {
                    const haystack = normalize(row.dataset.quoteSearchText || row.textContent || '');
                    const matches = !term || haystack.includes(term);
                    row.hidden = !matches;
                    if (matches) visible += 1;
                });

                if (mapInfo) {
                    mapInfo.textContent = term
                        ? `Mostrando ${visible} de ${rows.length} produtos`
                        : `Mostrando todos os ${rows.length} produtos`;
                }

                syncWidth();
            };

            search?.addEventListener('input', filterRows);
            filterRows();
        })();
    </script>
@endsection
