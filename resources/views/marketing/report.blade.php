@extends('layouts.app', ['pageTitle' => 'Relatorio de marketing'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div><h1 class="title">Marketing</h1><p class="subtitle">Desempenho das campanhas importadas do Meta Ads, com custos ponderados pelo periodo selecionado.</p></div>
        @if (auth()->user()->canWriteFinance($company))<a class="btn secondary" href="{{ route('marketing.import.create') }}">Importar dados</a>@endif
    </div>

    <form class="form" method="get" action="{{ route('relatorios.marketing.index') }}" style="margin-top:22px;">
        <div class="field-grid marketing-filters">
            <label>Inicio<input type="date" name="start" value="{{ $filters['start'] ?? '' }}"></label>
            <label>Fim<input type="date" name="end" value="{{ $filters['end'] ?? '' }}"></label>
            <label>Campanha<select name="campaign"><option value="">Todas as campanhas</option>@foreach ($campaigns as $campaign)<option value="{{ $campaign }}" @selected(($filters['campaign'] ?? '') === $campaign)>{{ $campaign }}</option>@endforeach</select></label>
        </div>
        <div class="actions"><button class="btn" type="submit">Aplicar filtros</button><a class="btn secondary" href="{{ route('relatorios.marketing.index') }}">Limpar</a></div>
    </form>

    @if ($summary['campaigns'] === 0)
        <div class="alert info" style="margin-top:22px;">Nenhuma campanha encontrada. Importe uma planilha do Meta Ads ou ajuste os filtros.</div>
    @else
        <div class="stats marketing-stats" style="margin-top:22px;">
            <div class="stat"><span>INVESTIMENTO</span><strong>R$ {{ number_format($summary['spent'], 2, ',', '.') }}</strong></div>
            <div class="stat"><span>ALCANCE REPORTADO</span><strong>{{ number_format($summary['reach'], 0, ',', '.') }}</strong></div>
            <div class="stat"><span>RESULTADOS</span><strong>{{ number_format($summary['results'], 0, ',', '.') }}</strong></div>
            <div class="stat"><span>CPC</span><strong>R$ {{ number_format($summary['cpc'], 2, ',', '.') }}</strong></div>
            <div class="stat"><span>CPR</span><strong>R$ {{ number_format($summary['cpr'], 2, ',', '.') }}</strong></div>
            <div class="stat"><span>CTR</span><strong>{{ number_format($summary['ctr'], 2, ',', '.') }}%</strong></div>
        </div>
        <p class="subtitle" style="margin-top:10px;">Fonte: Meta Ads importado. Dados ate {{ $dataThrough?->format('d/m/Y') ?? '-' }}. CPC e CPR sao calculados de forma ponderada.</p>

        <section class="card" style="margin-top:22px;"><h2 class="panel-title">Investimento e eficiencia por mes</h2><p class="subtitle">Valor aplicado e evolucao do custo por clique e por resultado.</p><div class="marketing-chart"><canvas id="marketingMonthlyChart"></canvas></div></section>
        <section class="card" style="margin-top:22px;"><h2 class="panel-title">Campanhas com maior investimento</h2><p class="subtitle">Investimento e CPR das dez campanhas que mais consumiram verba no periodo.</p><div class="marketing-chart"><canvas id="marketingCampaignChart"></canvas></div></section>

        <section class="card" style="margin-top:22px;">
            <h2 class="panel-title">Detalhamento por campanha</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Campanha</th><th>Investimento</th><th>Resultados</th><th>Alcance</th><th>Cliques</th><th>CPC</th><th>CPR</th><th>CTR</th></tr></thead>
                <tbody>@foreach ($topCampaigns as $row)<tr><td><strong>{{ $row['key'] }}</strong></td><td>R$ {{ number_format($row['spent'], 2, ',', '.') }}</td><td>{{ number_format($row['results'], 0, ',', '.') }}</td><td>{{ number_format($row['reach'], 0, ',', '.') }}</td><td>{{ number_format($row['link_clicks'], 0, ',', '.') }}</td><td>R$ {{ number_format($row['cpc'], 2, ',', '.') }}</td><td>R$ {{ number_format($row['cpr'], 2, ',', '.') }}</td><td>{{ number_format($row['ctr'], 2, ',', '.') }}%</td></tr>@endforeach</tbody>
            </table></div>
        </section>
    @endif

    <section class="card" style="margin-top:22px;">
        <div class="actions" style="justify-content:space-between; align-items:flex-start;"><div><h2 class="panel-title">Analista de campanhas com IA</h2><p class="subtitle">Pergunte sobre custos, campanhas, variacoes mensais e oportunidades nos dados filtrados.</p></div><span class="role-pill">Dados agregados</span></div>
        @unless ($aiEnabled)<div class="alert info">Configure <strong>OPENAI_API_KEY</strong> e <strong>OPENAI_MODEL</strong> no servidor para habilitar esta area.</div>@endunless
        <form method="post" action="{{ route('relatorios.marketing.analyze') }}" style="margin-top:14px;">
            @csrf
            <input type="hidden" name="start" value="{{ $filters['start'] ?? '' }}"><input type="hidden" name="end" value="{{ $filters['end'] ?? '' }}"><input type="hidden" name="campaign" value="{{ $filters['campaign'] ?? '' }}">
            <label>Sua pergunta<textarea name="question" rows="3" maxlength="1200" placeholder="Ex.: Quais campanhas devo revisar primeiro e por que?" required>{{ old('question', session('marketing_ai_question')) }}</textarea>@error('question') <span class="error">{{ $message }}</span> @enderror</label>
            <div class="actions"><button class="btn" type="submit" @disabled(! $aiEnabled || $summary['campaigns'] === 0)>Analisar campanhas</button></div>
        </form>
        @if ($aiAnalysis)<div class="alert info" style="margin-top:16px; line-height:1.6;"><strong>Analise</strong><div style="margin-top:8px;">{!! nl2br(e($aiAnalysis)) !!}</div></div>@endif
    </section>

    @if ($summary['campaigns'] > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (() => {
                const monthly = @json($monthlyChart); const campaigns = @json($campaignChart);
                const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
                new Chart(document.getElementById('marketingMonthlyChart'), { type: 'bar', data: { labels: monthly.labels, datasets: [
                    { label: 'Investimento', data: monthly.spent, backgroundColor: '#167d73', yAxisID: 'money' },
                    { label: 'CPC', data: monthly.cpc, type: 'line', borderColor: '#2563eb', backgroundColor: '#2563eb', tension: .25, yAxisID: 'cost' },
                    { label: 'CPR', data: monthly.cpr, type: 'line', borderColor: '#b7791f', backgroundColor: '#b7791f', tension: .25, yAxisID: 'cost' }
                ] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.raw)}` } } }, scales: { money: { beginAtZero: true, position: 'left', ticks: { callback: money } }, cost: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: money } } } } });
                new Chart(document.getElementById('marketingCampaignChart'), { type: 'bar', data: { labels: campaigns.labels, datasets: [
                    { label: 'Investimento', data: campaigns.spent, backgroundColor: '#167d73' }, { label: 'CPR', data: campaigns.cpr, backgroundColor: '#b7791f' }
                ] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.raw)}` } } }, scales: { x: { ticks: { maxRotation: 40, minRotation: 20 } }, y: { beginAtZero: true, ticks: { callback: money } } } } });
            })();
        </script>
    @endif

    <style>
        .marketing-filters { grid-template-columns:repeat(3, minmax(0, 1fr)); }
        .marketing-stats { grid-template-columns:repeat(6, minmax(150px, 1fr)); }
        .marketing-chart { height:360px; margin-top:16px; }
        @media (max-width:1100px) { .marketing-stats { grid-template-columns:repeat(3, minmax(0, 1fr)); } }
        @media (max-width:720px) { .marketing-stats { grid-template-columns:repeat(2, minmax(0, 1fr)); } .marketing-filters { grid-template-columns:1fr; } }
    </style>
@endsection
