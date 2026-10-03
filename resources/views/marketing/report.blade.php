@extends('layouts.app', ['pageTitle' => 'Relatorio de marketing'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Marketing</h1>
            <p class="subtitle">Desempenho das campanhas importadas do Meta Ads no periodo selecionado.</p>
        </div>
        @if (auth()->user()->canWriteFinance($company))
            <a class="btn secondary" href="{{ route('marketing.import.create') }}">Importar dados</a>
        @endif
    </div>

    <form class="form" method="get" action="{{ route('relatorios.marketing.index') }}" style="margin-top:22px;">
        <div class="field-grid marketing-filters">
            <label>Inicio<input type="date" name="start" value="{{ $filters['start'] ?? '' }}"></label>
            <label>Fim<input type="date" name="end" value="{{ $filters['end'] ?? '' }}"></label>
            <label>Campanha
                <select name="campaign">
                    <option value="">Todas as campanhas</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign }}" @selected(($filters['campaign'] ?? '') === $campaign)>{{ $campaign }}</option>
                    @endforeach
                </select>
            </label>
            <label>Veiculacao
                <select name="status">
                    <option value="">Ativas e inativas</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Somente ativas</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Somente inativas</option>
                </select>
            </label>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Aplicar filtros</button>
            <a class="btn secondary" href="{{ route('relatorios.marketing.index') }}">Limpar</a>
        </div>
    </form>

    @if ($summary['campaigns'] === 0)
        <div class="alert info" style="margin-top:22px;">Nenhuma campanha encontrada. Importe uma planilha do Meta Ads ou ajuste os filtros.</div>
    @else
        <div class="marketing-kpis">
            <article class="marketing-kpi"><span>Investimento</span><strong>R$ {{ number_format($summary['spent'], 2, ',', '.') }}</strong><small>Valor aplicado. Deve ser avaliado junto de resultados e CPR.</small></article>
            <article class="marketing-kpi"><span>Alcance reportado</span><strong>{{ number_format($summary['reach'], 0, ',', '.') }}</strong><small>Quanto maior, mais pessoas alcancadas. Pode haver sobreposicao entre campanhas.</small></article>
            <article class="marketing-kpi"><span>Resultados</span><strong>{{ number_format($summary['results'], 0, ',', '.') }}</strong><small>Quanto maior, melhor. Considera a acao definida no Meta Ads.</small></article>
            <article class="marketing-kpi good-low"><span>CPC</span><strong>R$ {{ number_format($summary['cpc'], 2, ',', '.') }}</strong><small>Quanto menor, melhor: custo medio por clique no link.</small></article>
            <article class="marketing-kpi good-low"><span>CPR</span><strong>R$ {{ number_format($summary['cpr'], 2, ',', '.') }}</strong><small>Quanto menor, melhor: custo medio para gerar um resultado.</small></article>
            <article class="marketing-kpi good-high"><span>CTR</span><strong>{{ number_format($summary['ctr'], 2, ',', '.') }}%</strong><small>Quanto maior, melhor: percentual de impressoes que gerou clique.</small></article>
        </div>

        <div class="metric-legend" aria-label="Como interpretar as metricas">
            <strong>Leitura rapida</strong>
            <span><b class="direction down">Menor e melhor</b> CPC e CPR</span>
            <span><b class="direction up">Maior e melhor</b> resultados, alcance e CTR</span>
            <span><b class="direction neutral">Contexto</b> investimento nao e bom ou ruim sozinho</span>
        </div>
        <p class="subtitle" style="margin-top:10px;">Fonte: Meta Ads importado. Dados ate {{ $dataThrough?->format('d/m/Y') ?? '-' }}. CPC, CPR e CTR sao calculados de forma ponderada.</p>

        <section class="card" style="margin-top:22px;">
            <h2 class="panel-title">Campeas do periodo</h2>
            <p class="subtitle">Destaques calculados automaticamente com os filtros aplicados.</p>
            @php
                $championCards = [
                    ['title' => 'Mais resultados', 'row' => $champions['results'], 'value' => $champions['results'] ? number_format($champions['results']['results'], 0, ',', '.').' resultados' : '-'],
                    ['title' => 'Melhor CPR', 'row' => $champions['cpr'], 'value' => $champions['cpr'] ? 'R$ '.number_format($champions['cpr']['cpr'], 2, ',', '.') : '-'],
                    ['title' => 'Maior alcance', 'row' => $champions['reach'], 'value' => $champions['reach'] ? number_format($champions['reach']['reach'], 0, ',', '.').' pessoas' : '-'],
                    ['title' => 'Melhor CTR', 'row' => $champions['ctr'], 'value' => $champions['ctr'] ? number_format($champions['ctr']['ctr'], 2, ',', '.').'%' : '-'],
                ];
            @endphp
            <div class="champions-grid">
                @foreach ($championCards as $champion)
                    <div class="champion-item">
                        <span>{{ $champion['title'] }}</span>
                        <strong>{{ $champion['row']['key'] ?? 'Sem dados suficientes' }}</strong>
                        <div>{{ $champion['value'] }}</div>
                        @if ($champion['row'])<span class="status-pill {{ $champion['row']['is_active'] ? 'active' : 'inactive' }}">{{ $champion['row']['status'] }}</span>@endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card" style="margin-top:22px;"><h2 class="panel-title">Investimento e eficiencia por mes</h2><p class="subtitle">Valor aplicado e evolucao do custo por clique e por resultado.</p><div class="marketing-chart"><canvas id="marketingMonthlyChart"></canvas></div></section>
        <section class="card" style="margin-top:22px;"><h2 class="panel-title">Investimento por campanha</h2><p class="subtitle">As dez campanhas que mais consumiram verba. Verde indica campanha ativa e cinza indica inativa.</p><div class="marketing-chart tall"><canvas id="marketingInvestmentChart"></canvas></div></section>
        <section class="card" style="margin-top:22px;"><h2 class="panel-title">Eficiencia por campanha</h2><p class="subtitle">Compara o CPR das campanhas que geraram resultados. Barras menores representam melhor eficiencia.</p><div class="marketing-chart tall"><canvas id="marketingEfficiencyChart"></canvas></div></section>

        <section class="card" style="margin-top:22px;">
            <h2 class="panel-title">Detalhamento por campanha</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Campanha</th><th>Status</th><th>Investimento</th><th>Resultados</th><th>Alcance</th><th>Cliques</th><th>CPC</th><th>CPR</th><th>CTR</th></tr></thead>
                <tbody>
                    @foreach ($topCampaigns as $row)
                        <tr>
                            <td><strong>{{ $row['key'] }}</strong></td><td><span class="status-pill {{ $row['is_active'] ? 'active' : 'inactive' }}">{{ $row['status'] }}</span></td>
                            <td>R$ {{ number_format($row['spent'], 2, ',', '.') }}</td><td>{{ number_format($row['results'], 0, ',', '.') }}</td><td>{{ number_format($row['reach'], 0, ',', '.') }}</td><td>{{ number_format($row['link_clicks'], 0, ',', '.') }}</td>
                            <td>R$ {{ number_format($row['cpc'], 2, ',', '.') }}</td><td>R$ {{ number_format($row['cpr'], 2, ',', '.') }}</td><td>{{ number_format($row['ctr'], 2, ',', '.') }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </section>
    @endif

    <section class="card" style="margin-top:22px;">
        <div class="actions" style="justify-content:space-between; align-items:flex-start;"><div><h2 class="panel-title">Analista de campanhas com IA</h2><p class="subtitle">A IA recebe os dados filtrados e o status atual de veiculacao de cada campanha.</p></div><span class="role-pill">Dados agregados</span></div>
        @unless ($aiEnabled)<div class="alert info">Configure <strong>OPENAI_API_KEY</strong> e <strong>OPENAI_MODEL</strong> no servidor para habilitar esta area.</div>@endunless
        <form method="post" action="{{ route('relatorios.marketing.analyze') }}" style="margin-top:14px;">
            @csrf
            <input type="hidden" name="start" value="{{ $filters['start'] ?? '' }}"><input type="hidden" name="end" value="{{ $filters['end'] ?? '' }}"><input type="hidden" name="campaign" value="{{ $filters['campaign'] ?? '' }}"><input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
            <label>Sua pergunta<textarea name="question" rows="3" maxlength="1200" placeholder="Ex.: Quais campanhas ativas devo otimizar primeiro?" required>{{ old('question', session('marketing_ai_question')) }}</textarea>@error('question') <span class="error">{{ $message }}</span> @enderror</label>
            <div class="actions"><button class="btn" type="submit" @disabled(! $aiEnabled || $summary['campaigns'] === 0)>Analisar campanhas</button></div>
        </form>
        @if ($aiAnalysis)<div class="alert info" style="margin-top:16px; line-height:1.6;"><strong>Analise</strong><div style="margin-top:8px;">{!! nl2br(e($aiAnalysis)) !!}</div></div>@endif
    </section>

    @if ($summary['campaigns'] > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (() => {
                const monthly = @json($monthlyChart); const campaigns = @json($campaignChart); const efficiency = @json($efficiencyChart);
                const money = (value) => new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' }).format(value || 0);
                const shortLabel = function(value) { const label = this.getLabelForValue(value); return label.length > 34 ? `${label.slice(0, 32)}...` : label; };
                new Chart(document.getElementById('marketingMonthlyChart'), { type:'bar', data:{ labels:monthly.labels, datasets:[
                    { label:'Investimento', data:monthly.spent, backgroundColor:'#167d73', yAxisID:'money' },
                    { label:'CPC', data:monthly.cpc, type:'line', borderColor:'#2563eb', backgroundColor:'#2563eb', tension:.25, yAxisID:'cost' },
                    { label:'CPR', data:monthly.cpr, type:'line', borderColor:'#b7791f', backgroundColor:'#b7791f', tension:.25, yAxisID:'cost' }
                ]}, options:{ responsive:true, maintainAspectRatio:false, interaction:{ mode:'index', intersect:false }, plugins:{ tooltip:{ callbacks:{ label:(ctx) => `${ctx.dataset.label}: ${money(ctx.raw)}` } } }, scales:{ money:{ beginAtZero:true, position:'left', ticks:{ callback:money } }, cost:{ beginAtZero:true, position:'right', grid:{ drawOnChartArea:false }, ticks:{ callback:money } } } } });
                new Chart(document.getElementById('marketingInvestmentChart'), { type:'bar', data:{ labels:campaigns.labels, datasets:[{ data:campaigns.spent, backgroundColor:campaigns.colors, borderRadius:3 }] }, options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(ctx) => `Investimento: ${money(ctx.raw)}`, afterLabel:(ctx) => `Status: ${campaigns.statuses[ctx.dataIndex]}` } } }, scales:{ x:{ beginAtZero:true, ticks:{ callback:money } }, y:{ ticks:{ callback:shortLabel } } } } });
                new Chart(document.getElementById('marketingEfficiencyChart'), { type:'bar', data:{ labels:efficiency.labels, datasets:[{ data:efficiency.cpr, backgroundColor:efficiency.colors, borderRadius:3 }] }, options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(ctx) => `CPR: ${money(ctx.raw)}`, afterLabel:(ctx) => [`Resultados: ${efficiency.results[ctx.dataIndex]}`, `Status: ${efficiency.statuses[ctx.dataIndex]}`] } } }, scales:{ x:{ beginAtZero:true, ticks:{ callback:money } }, y:{ ticks:{ callback:shortLabel } } } } });
            })();
        </script>
    @endif

    <style>
        .marketing-filters { grid-template-columns:repeat(4, minmax(0, 1fr)); }
        .marketing-kpis { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px; margin-top:22px; }
        .marketing-kpi { min-width:0; background:#fff; border:1px solid var(--line); border-radius:8px; padding:17px; display:grid; gap:7px; }
        .marketing-kpi span { color:var(--muted); font-size:12px; font-weight:800; text-transform:uppercase; }
        .marketing-kpi strong { font-size:25px; line-height:1.1; }
        .marketing-kpi small { color:var(--muted); line-height:1.4; }
        .marketing-kpi.good-low { border-top:3px solid #2563eb; } .marketing-kpi.good-high { border-top:3px solid #167d73; }
        .metric-legend { display:flex; flex-wrap:wrap; align-items:center; gap:10px 18px; margin-top:14px; padding:12px 14px; border:1px solid var(--line); border-radius:8px; background:#fff; }
        .direction { display:inline-flex; padding:4px 7px; margin-right:5px; border-radius:5px; font-size:11px; }
        .direction.down { color:#1d4ed8; background:#dbeafe; } .direction.up { color:#047857; background:#d1fae5; } .direction.neutral { color:#475569; background:#e8eef5; }
        .champions-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); margin-top:16px; border:1px solid var(--line); border-radius:8px; overflow:hidden; }
        .champion-item { min-width:0; padding:16px; border-right:1px solid var(--line); display:grid; align-content:start; gap:7px; }
        .champion-item:last-child { border-right:0; } .champion-item > span:first-child { color:var(--muted); font-size:12px; text-transform:uppercase; font-weight:800; }
        .champion-item strong { line-height:1.3; overflow-wrap:anywhere; }
        .status-pill { display:inline-flex; justify-self:start; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:800; }
        .status-pill.active { color:#047857; background:#d1fae5; } .status-pill.inactive { color:#475569; background:#e8eef5; }
        .marketing-chart { position:relative; height:360px; margin-top:16px; } .marketing-chart.tall { height:430px; }
        @media (max-width:1100px) { .marketing-filters, .champions-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } .marketing-kpis { grid-template-columns:repeat(2, minmax(0, 1fr)); } .champion-item:nth-child(2) { border-right:0; } .champion-item:nth-child(-n+2) { border-bottom:1px solid var(--line); } }
        @media (max-width:720px) { .marketing-filters, .marketing-kpis, .champions-grid { grid-template-columns:1fr; } .champion-item { border-right:0; border-bottom:1px solid var(--line); } .champion-item:last-child { border-bottom:0; } .marketing-chart.tall { height:520px; } }
    </style>
@endsection
