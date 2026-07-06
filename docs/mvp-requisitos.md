# Controle Farmacia - Requisitos do MVP

## Objetivo
Criar um mini sistema financeiro para farmacias pequenas e medias, com foco em reduzir trabalho manual no controle de contas a pagar, boletos, receitas mensais e conciliacao bancaria por CSV.

## Publico inicial
Farmacias independentes ou pequenas redes que precisam controlar despesas, fornecedores, boletos e indicadores financeiros sem depender de planilhas complexas.

## Principios do produto
- O usuario deve cadastrar o minimo possivel.
- Todo PDF de boleto deve virar uma conta a pagar revisavel.
- Automacao deve sugerir, mas o usuario confirma no MVP.
- O sistema deve nascer multiempresa.
- O historico por fornecedor deve melhorar futuras classificacoes.

## Modulos do MVP

### Empresas e usuarios
- Uma plataforma para varias empresas.
- Cada usuario pertence a uma ou mais empresas.
- Cada registro financeiro deve pertencer a uma empresa.

### Fornecedores
- Nome/razao social.
- Nome fantasia.
- CNPJ/CPF.
- Categoria padrao de despesa.
- Dados de contato.
- Status ativo/inativo.

### Categorias financeiras
- Compra de mercadoria.
- Aluguel.
- Funcionarios.
- Energia.
- Internet/telefone.
- Contador.
- Combustivel.
- Taxas bancarias.
- Outros.

### Contas a pagar
- Fornecedor.
- Categoria.
- Descricao.
- Valor.
- Data de vencimento.
- Data de pagamento.
- Status: aberto, pago, vencido, cancelado.
- Origem: manual, boleto_pdf, recorrente.
- Anexo do PDF quando houver.

### Upload de boletos
- Usuario envia PDF manualmente.
- Sistema extrai texto do PDF.
- Sistema tenta identificar linha digitavel, vencimento, valor, fornecedor e CNPJ.
- Usuario revisa e confirma antes de criar a conta a pagar.

### Receitas mensais
- Mes de referencia.
- Faturamento bruto.
- CMV.
- Numero de vendas.
- Ticket medio calculado.
- Observacoes.

### Conciliacao bancaria
- Importacao de CSV do banco.
- Cadastro de lancamentos do extrato.
- Sugestao de conciliacao por valor, data aproximada e fornecedor.
- Baixa manual confirmada pelo usuario.

### Alertas
- Contas vencendo em 7 dias.
- Contas vencendo em 3 dias.
- Contas vencendo hoje.
- Contas vencidas.

## Fora do MVP inicial
- Integracao bancaria automatica.
- WhatsApp automatico.
- OCR para PDF escaneado.
- Controle completo de estoque.
- Emissao de nota fiscal.
- Pagamento automatico de boletos.

## Proxima etapa tecnica
1. Configurar banco MySQL/MariaDB do XAMPP.
2. Criar migrations principais.
3. Criar telas base de dashboard, fornecedores e contas a pagar.
4. Adicionar login/autenticacao.
5. Implementar upload de PDF e extracao inicial.
