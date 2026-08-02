<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalacao concluida</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #0f172a;
        }

        main {
            max-width: 720px;
            margin: 48px auto;
            padding: 24px;
            background: #fff;
            border: 1px solid #d8e2ee;
            border-radius: 8px;
        }

        pre {
            white-space: pre-wrap;
            background: #eef3f8;
            border-radius: 6px;
            padding: 16px;
        }
    </style>
</head>
<body>
    <main>
        <h1>Instalacao concluida</h1>
        <p>As tabelas do banco foram verificadas/criadas com sucesso.</p>
        <p><strong>Importante:</strong> agora edite o arquivo <code>.env</code> e coloque <code>INSTALLER_ENABLED=false</code>.</p>
        @if ($output !== '')
            <pre>{{ $output }}</pre>
        @endif
    </main>
</body>
</html>
