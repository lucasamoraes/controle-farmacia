# Deploy em producao na HostGator

Este guia prepara um MVP controlado para a farmacia testar com MySQL e OCR base.

## Requisitos

- PHP 8.2 ou superior.
- Extensoes PHP: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `xml`, `gd` ou `imagick`, `curl`.
- Banco MySQL criado no painel da HostGator.
- Composer disponivel no servidor ou vendor enviado junto no upload.
- Dominio ou subdominio apontando para a pasta `public` do Laravel.

## Banco de dados

No painel da HostGator, crie:

- banco MySQL;
- usuario do banco;
- senha forte;
- permissao total do usuario nesse banco.

Depois copie `.env.production.example` para `.env` no servidor e preencha os campos do banco com os dados criados no painel da hospedagem:

```env
APP_URL=https://seudominio.com.br
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

Gere a chave:

```bash
php artisan key:generate
```

Rode as tabelas:

```bash
php artisan migrate --force
```

## Pastas e public

O ideal e o dominio apontar para:

```text
/caminho/do/projeto/public
```

Se a hospedagem nao permitir mudar o document root, crie um subdominio ou ajuste a estrutura com cuidado para que somente `public` fique exposta. Nunca deixe `.env`, `storage`, `vendor` ou `database` acessiveis publicamente.

As pastas abaixo precisam de escrita:

```text
storage
bootstrap/cache
```

## Otimizacao

Em producao:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Se alterar `.env`, rode:

```bash
php artisan optimize:clear
php artisan config:cache
```

## OCR para boletos escaneados

O sistema tenta ler texto do PDF normalmente. Se o PDF for escaneado/imagem, ele aciona OCR quando configurado.

Configuracao inicial recomendada para hospedagem compartilhada:

```env
OCR_PROVIDER=ocrspace
OCR_API_KEY=
OCR_ENDPOINT=https://api.ocr.space/parse/image
OCR_LANGUAGE=por
OCR_TIMEOUT=60
```

Sem `OCR_API_KEY`, boletos PDF normais continuam funcionando, mas boletos escaneados serao recusados com aviso para cadastro manual/configuracao OCR.

## Checklist antes de liberar para a farmacia

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` com o dominio final.
- Login e cadastro testados.
- Upload de boleto PDF normal testado.
- Upload de boleto escaneado testado com OCR.
- Importacao de planilha testada.
- Dashboard/resumo abrindo no celular.
- Backup do banco configurado no painel da HostGator.
- Tamanho maximo de upload suficiente para PDFs de boleto.
