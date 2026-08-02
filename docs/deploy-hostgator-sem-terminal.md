# Deploy na HostGator sem terminal

Este guia considera o dominio `controle.amplificati.com.br` no cPanel.

## 1. Banco de dados

Use o MySQL criado no cPanel:

- Banco: `luca6802_controle`
- Usuario: `luca6802_controleuser`
- Senha: informar somente no arquivo `.env` da hospedagem

## 2. Arquivos

Envie o pacote ZIP de producao pelo Gerenciador de Arquivos do cPanel e extraia dentro de:

`/home1/luca6802/controle.amplificati.com.br`

Apos extrair, essa pasta deve conter diretamente os arquivos do Laravel, por exemplo:

- `app`
- `bootstrap`
- `config`
- `database`
- `public`
- `resources`
- `routes`
- `storage`
- `vendor`
- `artisan`

Se os arquivos ficarem dentro de uma subpasta, mova o conteudo para a raiz acima ou ajuste o Document Root para essa subpasta.

## 3. Document Root do dominio

No cPanel, em Manage Domain, altere o Document Root para:

`controle.amplificati.com.br/public`

Isso evita expor arquivos internos do Laravel, como `.env`, `storage`, `vendor` e `database`.

## 4. Arquivo .env

Crie um arquivo chamado `.env` na mesma pasta do `artisan`, usando o modelo `hostgator-env-controle.txt` gerado para producao.

Preencha a linha:

`DB_PASSWORD=COLOQUE_A_SENHA_AQUI`

com a senha real do usuario MySQL.

## 5. Rodar a instalacao do banco

Com o `.env` salvo e o Document Root apontando para `public`, acesse:

`https://controle.amplificati.com.br/instalar?token=SEU_TOKEN`

Essa URL cria as tabelas no banco usando as migrations do Laravel.

## 6. Desativar o instalador

Depois que aparecer a tela de sucesso, volte no `.env` e altere:

`INSTALLER_ENABLED=false`

Nunca deixe o instalador ativo em producao.

## Problemas comuns

- Erro 404: geralmente o Document Root ainda nao aponta para `public`, ou os arquivos foram extraidos dentro de uma subpasta.
- Erro 500: confira a senha do banco, a versao do PHP e se a pasta `vendor` foi enviada.
- Erro de banco: confirme `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` e se o usuario tem permissao total no banco.
