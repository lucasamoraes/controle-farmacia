# Modelo de Dados Inicial

## companies
Empresas/farmacias cadastradas na plataforma.

Campos principais:
- id
- name
- trade_name
- document
- email
- phone
- is_active

## company_user
Relacionamento entre usuarios e empresas.

Campos principais:
- company_id
- user_id
- role: owner, admin, financial, viewer

## financial_categories
Categorias de despesas e receitas.

Campos principais:
- company_id
- name
- type: expense, revenue
- is_default
- is_active

## suppliers
Fornecedores.

Campos principais:
- company_id
- financial_category_id
- name
- trade_name
- document
- email
- phone
- notes
- is_active

## payables
Contas a pagar.

Campos principais:
- company_id
- supplier_id
- financial_category_id
- description
- amount
- due_date
- paid_at
- status: open, paid, overdue, cancelled
- source: manual, boleto_pdf, recurring
- document_number
- barcode
- digitable_line
- attachment_path
- notes

## monthly_revenues
Indicadores mensais da farmacia.

Campos principais:
- company_id
- reference_month
- gross_revenue
- cost_of_goods_sold
- sales_count
- average_ticket
- notes

## bank_imports
Importacoes de CSV bancario.

Campos principais:
- company_id
- file_name
- imported_at
- status

## bank_transactions
Lancamentos importados do extrato.

Campos principais:
- company_id
- bank_import_id
- transaction_date
- description
- amount
- type: debit, credit
- matched_payable_id
- reconciled_at

## boleto_uploads
Historico de PDFs enviados.

Campos principais:
- company_id
- payable_id
- original_file_name
- stored_path
- extracted_text
- parsed_data
- processing_status
- error_message
