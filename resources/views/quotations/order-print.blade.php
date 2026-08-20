<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Pedido {{ $supplier->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; color:#111827; margin:28px; }
        h1 { margin:0 0 4px; font-size:22px; }
        p { margin:4px 0; color:#4b5563; }
        table { width:100%; border-collapse:collapse; margin-top:22px; }
        th, td { border:1px solid #d1d5db; padding:9px; text-align:left; }
        th { background:#f3f4f6; }
        .total { text-align:right; font-size:18px; font-weight:700; margin-top:18px; }
        @media print { button { display:none; } }
    </style>
</head>
<body>
    <button onclick="window.print()">Imprimir ou salvar PDF</button>
    <h1>Pedido de compra</h1>
    <p><strong>Farmacia:</strong> {{ $company->trade_name ?: $company->name }}</p>
    <p><strong>Fornecedor:</strong> {{ $supplier->name }}</p>
    <p><strong>Cotacao:</strong> #{{ $quotation->id }} | <strong>Data:</strong> {{ now()->format('d/m/Y') }}</p>

    <table>
        <thead><tr><th>Produto</th><th>Quantidade</th><th>Valor unitario</th><th>Total</th></tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['item']->description }}</td>
                <td>{{ number_format((float) $row['item']->quantity, 3, ',', '.') }} {{ $row['item']->unit }}</td>
                <td>R$ {{ number_format($row['price'], 2, ',', '.') }}</td>
                <td>R$ {{ number_format($row['total'], 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Nenhum item vencedor para este fornecedor.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="total">Total: R$ {{ number_format(collect($rows)->sum('total'), 2, ',', '.') }}</div>
</body>
</html>
