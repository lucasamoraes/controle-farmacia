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
    .quote-table th, .quote-table td { min-width:150px; background:var(--panel); }
    .quote-table thead th { position:sticky; top:0; z-index:4; background:#eef3f8; }
    .quote-table .sticky-product { position:sticky; left:0; z-index:3; min-width:300px; width:300px; box-shadow:1px 0 0 var(--line); }
    .quote-table .sticky-qty { position:sticky; left:300px; z-index:3; min-width:120px; width:120px; box-shadow:1px 0 0 var(--line); }
    .quote-table thead .sticky-product, .quote-table thead .sticky-qty { z-index:6; background:#eef3f8; }
    .quote-table tbody tr[hidden] { display:none; }
    .quote-pager { display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap; margin-top:12px; color:var(--muted); font-size:13px; }
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
                <p class="subtitle">Use a rolagem horizontal; produto, quantidade e cabecalho ficam fixos para facilitar cotações grandes.</p>
            </div>
            <label>Itens por pagina
                <select data-quote-page-size>
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="40">40</option>
                    <option value="80">80</option>
                    <option value="100">100</option>
                    <option value="all">Todos</option>
                </select>
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
                        <th>Ult. compra</th>
                        @foreach ($participants as $participant)
                            <th>
                                <div style="display:grid; gap:6px;">
                                    <span>{{ $participant->quoted_name }}</span>
                                    <span class="actions">
                                        <a class="btn small secondary" href="{{ route('cotacoes.orders.export', [$quotation, $participant]) }}">Excel</a>
                                        <a class="btn small secondary" href="{{ route('cotacoes.orders.print', [$quotation, $participant]) }}" target="_blank">PDF</a>
                                    </span>
                                </div>
                            </th>
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
                    <tr data-quote-row>
                        <td class="sticky-product">
                            <strong>{{ $item->description }}</strong>
                            @if ($item->product?->image_url)
                                <br><a href="{{ $item->product->image_url }}" target="_blank" style="color:var(--brand);">imagem</a>
                            @endif
                        </td>
                        <td class="sticky-qty">
                            <div style="display:grid; gap:4px; width:92px;">
                                <input type="number" step="1" min="1" name="quantities[{{ $item->id }}]" value="{{ (float) $item->quantity }}" style="width:92px;">
                                <span style="color:var(--muted); font-size:12px;">{{ $item->unit }}</span>
                            </div>
                        </td>
                        <td>{{ $lastPrice > 0 ? $fmtMoney($lastPrice) : '-' }}</td>
                        @foreach ($participants as $participant)
                            @php
                                $price = $matrix[$item->id][$participant->id]->unit_price ?? null;
                                $isLowest = $winner && (int) ($winner['lowest_quotation_supplier_id'] ?? 0) === (int) $participant->id;
                                $isWinner = $winner && (int) ($winner['quotation_supplier_id'] ?? 0) === (int) $participant->id;
                            @endphp
                            <td style="{{ $isWinner ? 'background:#ecfdf5;' : '' }}">
                                <input type="number" step="0.01" min="0" name="prices[{{ $item->id }}][{{ $participant->id }}]" value="{{ $price }}" style="width:120px;">
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
                        <td>
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
            <div class="quote-pager">
                <span data-quote-page-info></span>
                <div class="actions">
                    <button class="btn small secondary" type="button" data-quote-page-prev>Anterior</button>
                    <button class="btn small secondary" type="button" data-quote-page-next>Proxima</button>
                </div>
            </div>
            @if ($participants->isNotEmpty())
                <div class="actions" style="justify-content:flex-end; margin-top:14px;">
                    <button class="btn" type="submit">Salvar precos</button>
                </div>
            @endif
        </form>
    </section>

    <script>
        (() => {
            const tableWrap = document.querySelector('[data-quote-table-wrap]');
            const topScroll = document.querySelector('[data-quote-scroll-top]');
            const topInner = document.querySelector('[data-quote-scroll-top-inner]');
            const rows = [...document.querySelectorAll('[data-quote-row]')];
            const pageSize = document.querySelector('[data-quote-page-size]');
            const pageInfo = document.querySelector('[data-quote-page-info]');
            const prev = document.querySelector('[data-quote-page-prev]');
            const next = document.querySelector('[data-quote-page-next]');
            let page = 1;

            const syncWidth = () => {
                if (tableWrap && topInner) topInner.style.width = `${tableWrap.scrollWidth}px`;
            };

            if (tableWrap && topScroll) {
                syncWidth();
                window.addEventListener('resize', syncWidth);
                topScroll.addEventListener('scroll', () => { tableWrap.scrollLeft = topScroll.scrollLeft; });
                tableWrap.addEventListener('scroll', () => { topScroll.scrollLeft = tableWrap.scrollLeft; });
            }

            const renderPage = () => {
                if (!pageSize || rows.length === 0) return;

                const selected = pageSize.value;
                const size = selected === 'all' ? rows.length : Number(selected);
                const totalPages = Math.max(1, Math.ceil(rows.length / size));
                page = Math.min(page, totalPages);
                const start = (page - 1) * size;
                const end = start + size;

                rows.forEach((row, index) => {
                    row.hidden = !(index >= start && index < end);
                });

                if (pageInfo) {
                    pageInfo.textContent = `Mostrando ${rows.length === 0 ? 0 : start + 1} a ${Math.min(end, rows.length)} de ${rows.length} itens`;
                }
                if (prev) prev.disabled = page <= 1;
                if (next) next.disabled = page >= totalPages;
                syncWidth();
            };

            pageSize?.addEventListener('change', () => { page = 1; renderPage(); });
            prev?.addEventListener('click', () => { page = Math.max(1, page - 1); renderPage(); });
            next?.addEventListener('click', () => { page += 1; renderPage(); });
            renderPage();
        })();
    </script>
@endsection
