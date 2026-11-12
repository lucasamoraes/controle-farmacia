<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DemoPharmacyDataGenerator
{
    private const COMPANY_DOCUMENT = 'DEMO-FARMA-2026';

    private const MONTHLY_REVENUE = [
        1 => 112500.00,
        2 => 118300.00,
        3 => 124800.00,
        4 => 131500.00,
        5 => 139200.00,
        6 => 146900.00,
        7 => 154600.00,
        8 => 163800.00,
        9 => 176400.00,
        10 => 194700.00,
        11 => 202300.00,
        12 => 218500.00,
    ];

    public function __construct(private readonly PayrollCalculator $payrollCalculator) {}

    public function generate(string $userName, string $email, string $password): Company
    {
        if (Company::where('document', self::COMPANY_DOCUMENT)->exists()) {
            throw ValidationException::withMessages([
                'demo' => 'A empresa demonstrativa FARMA TESTE ja foi criada.',
            ]);
        }

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'demo_email' => 'Este e-mail ja pertence a outro usuario.',
            ]);
        }

        return DB::transaction(function () use ($userName, $email, $password) {
            $user = User::create([
                'name' => $userName,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $company = Company::create([
                'name' => 'FARMA TESTE COMERCIO DE MEDICAMENTOS LTDA',
                'trade_name' => 'FARMA TESTE',
                'document' => self::COMPANY_DOCUMENT,
                'email' => $email,
                'phone' => '(65) 99999-2026',
                'is_active' => true,
            ]);
            $company->users()->attach($user->id, ['role' => 'owner']);

            $categories = $this->createCategories($company);
            $suppliers = $this->createSuppliers($company, $categories);
            $employees = $this->createEmployees($company);
            $this->createSalesAndRevenues($company);
            $this->createEmployeeMovementsAndPayroll($company, $employees, $categories['Funcionarios']);
            $this->createOperatingPayables($company, $categories, $suppliers);
            $this->createCreditCards($company, $categories);
            $this->createProductsAndQuotations($company, $user, $suppliers);
            $this->createMarketingHistory($company, $user);

            return $company->fresh();
        });
    }

    private function createCategories(Company $company): array
    {
        $names = [
            'Compra de mercadorias',
            'Funcionarios',
            'Aluguel',
            'Energia eletrica',
            'Agua e saneamento',
            'Internet e telefonia',
            'Marketing',
            'Impostos e taxas',
            'Manutencao',
            'Servicos contabeis',
            'Combustivel e entregas',
            'Sistemas e tecnologia',
            'Material de consumo',
        ];

        return collect($names)->mapWithKeys(function (string $name) use ($company) {
            $category = $company->categories()->create([
                'name' => $name,
                'type' => 'expense',
                'is_default' => in_array($name, ['Compra de mercadorias', 'Funcionarios'], true),
                'is_active' => true,
            ]);

            return [$name => $category];
        })->all();
    }

    private function createSuppliers(Company $company, array $categories): array
    {
        $rows = [
            ['Drogaria Distribuidora Centro-Oeste', 'Compra de mercadorias'],
            ['MedFarma Distribuidora', 'Compra de mercadorias'],
            ['Saude Brasil Medicamentos', 'Compra de mercadorias'],
            ['Imobiliaria Avenida', 'Aluguel'],
            ['Energisa Demonstracao', 'Energia eletrica'],
            ['Aguas Teste', 'Agua e saneamento'],
            ['Conecta Telecom', 'Internet e telefonia'],
            ['Contabil Mais', 'Servicos contabeis'],
            ['Agencia Impulso Digital', 'Marketing'],
            ['Oficina das Farmacias', 'Manutencao'],
        ];

        return collect($rows)->mapWithKeys(function (array $row, int $index) use ($company, $categories) {
            [$name, $category] = $row;
            $supplier = $company->suppliers()->create([
                'financial_category_id' => $categories[$category]->id,
                'name' => $name,
                'trade_name' => $name,
                'document' => sprintf('990000000%05d', $index + 1),
                'email' => 'contato'.($index + 1).'@exemplo.test',
                'phone' => '(65) 3000-'.str_pad((string) (1000 + $index), 4, '0', STR_PAD_LEFT),
                'notes' => 'Fornecedor ficticio do ambiente demonstrativo.',
                'is_active' => true,
            ]);

            return [$name => $supplier];
        })->all();
    }

    private function createEmployees(Company $company): array
    {
        $positions = [
            ['Farmaceutico responsavel', '223405', null, null],
            ['Atendente de farmacia', '521130', null, null],
            ['Operador de caixa', '421125', null, null],
            ['Motoboy', '519110', 'periculosidade', 30],
            ['Auxiliar administrativo', '411005', null, null],
        ];
        foreach ($positions as [$name, $cbo, $additionalType, $additionalPercent]) {
            $company->employeePositions()->create([
                'name' => $name,
                'cbo_code' => $cbo,
                'additional_type' => $additionalType,
                'additional_percent' => $additionalPercent,
                'is_active' => true,
            ]);
        }
        foreach (['Balcao', 'Caixa', 'Delivery', 'Administrativo'] as $department) {
            $company->employeeDepartments()->create(['name' => $department, 'is_active' => true]);
        }
        foreach ($this->movementTypes() as $movementType) {
            $company->employeeMovementTypes()->create($movementType);
        }

        $rows = [
            ['001', 'Mariana Oliveira', 'Farmaceutico responsavel', '223405', 'Balcao', 5200.00, '2024-02-05'],
            ['002', 'Carlos Henrique Souza', 'Atendente de farmacia', '521130', 'Balcao', 2350.00, '2024-06-10'],
            ['003', 'Ana Paula Mendes', 'Atendente de farmacia', '521130', 'Balcao', 2280.00, '2025-01-13'],
            ['004', 'Juliana Ribeiro', 'Operador de caixa', '421125', 'Caixa', 2100.00, '2025-03-03'],
            ['005', 'Rafael Martins', 'Motoboy', '519110', 'Delivery', 2200.00, '2025-08-18'],
            ['006', 'Beatriz Almeida', 'Auxiliar administrativo', '411005', 'Administrativo', 2800.00, '2025-11-04'],
        ];

        return collect($rows)->map(function (array $row) use ($company) {
            [$code, $name, $role, $cbo, $department, $salary, $start] = $row;
            $calculated = $this->payrollCalculator->calculate($salary);

            return $company->employees()->create(array_merge($calculated, [
                'employee_code' => $code,
                'name' => $name,
                'document' => '00000000'.str_pad($code, 3, '0', STR_PAD_LEFT),
                'role' => $role,
                'cbo_code' => $cbo,
                'department' => $department,
                'branch' => 'Matriz',
                'salary' => $salary,
                'fixed_salary' => $salary,
                'variable_salary' => 0,
                'payment_day' => 5,
                'starts_on' => $start,
                'is_active' => true,
                'notes' => 'Funcionario ficticio para demonstracao.',
            ]));
        })->all();
    }

    private function createSalesAndRevenues(Company $company): void
    {
        foreach (self::MONTHLY_REVENUE as $monthNumber => $target) {
            $month = Carbon::create(2026, $monthNumber, 1);
            $dates = collect(range(1, $month->daysInMonth))->map(fn (int $day) => $month->copy()->day($day));
            $weights = $dates->map(fn (Carbon $date) => $this->salesWeight($date));
            $weightTotal = (float) $weights->sum();
            $targetCents = (int) round($target * 100);
            $allocatedCents = 0;
            $dailyRows = [];

            foreach ($dates as $index => $date) {
                $cents = $index === $dates->count() - 1
                    ? $targetCents - $allocatedCents
                    : (int) round($targetCents * ((float) $weights[$index] / $weightTotal));
                $allocatedCents += $cents;
                $amount = $cents / 100;
                $deliveryRatio = 0.27 + (($monthNumber - 1) * 0.007) + (($date->day % 4) * 0.002);
                $deliveryRevenue = round($amount * $deliveryRatio, 2);
                $counterRevenue = round($amount - $deliveryRevenue, 2);
                $deliveryTicket = 51 + ($monthNumber * 0.65) + (($date->day % 5) * 0.40);
                $counterTicket = 34 + ($monthNumber * 0.38) + (($date->day % 4) * 0.25);
                $dailyRows[] = [
                    'company_id' => $company->id,
                    'sale_date' => $date->toDateString(),
                    'weekday' => $this->weekdayName($date),
                    'amount' => $amount,
                    'delivery_sales_count' => max(1, (int) round($deliveryRevenue / $deliveryTicket)),
                    'delivery_revenue' => $deliveryRevenue,
                    'counter_sales_count' => max(1, (int) round($counterRevenue / $counterTicket)),
                    'counter_revenue' => $counterRevenue,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('daily_sales')->insert($dailyRows);
            $deliveryRevenue = round((float) collect($dailyRows)->sum('delivery_revenue'), 2);
            $counterRevenue = round((float) collect($dailyRows)->sum('counter_revenue'), 2);
            $deliveryCount = (int) collect($dailyRows)->sum('delivery_sales_count');
            $counterCount = (int) collect($dailyRows)->sum('counter_sales_count');
            $salesCount = $deliveryCount + $counterCount;
            $cost = round($target * (0.505 + (($monthNumber % 3) * 0.006)), 2);

            $company->monthlyRevenues()->create([
                'reference_month' => $month->toDateString(),
                'gross_revenue' => $target,
                'revenue_to_receive' => round($target * 0.055, 2),
                'cost_of_goods_sold' => $cost,
                'cmv_percentage' => round(($cost / $target) * 100, 2),
                'sales_count' => $salesCount,
                'delivery_sales_count' => $deliveryCount,
                'delivery_revenue' => $deliveryRevenue,
                'counter_sales_count' => $counterCount,
                'counter_revenue' => $counterRevenue,
                'average_ticket' => round($target / max(1, $salesCount), 2),
                'items_per_ticket' => round(2.18 + ($monthNumber * 0.025), 2),
                'notes' => 'Fechamento ficticio do ambiente demonstrativo.',
                'important_info' => $monthNumber === 10 ? 'Mes de campanha de primavera e crescimento do delivery.' : null,
            ]);
        }
    }

    private function createEmployeeMovementsAndPayroll(Company $company, array $employees, FinancialCategory $category): void
    {
        foreach (self::MONTHLY_REVENUE as $monthNumber => $unused) {
            $month = Carbon::create(2026, $monthNumber, 1);
            $payrollTotal = 0.0;

            foreach ($employees as $employeeIndex => $employee) {
                $additionalPercent = $employee->role === 'Motoboy' ? 30 : 0;
                $grossSalary = (float) $employee->base_salary * (1 + ($additionalPercent / 100));
                $calculated = $this->payrollCalculator->calculate($grossSalary);
                $net = $grossSalary - $calculated['inss_discount'] - $calculated['irrf_discount'];

                if (in_array($employee->role, ['Atendente de farmacia', 'Farmaceutico responsavel'], true)) {
                    $commission = 140 + ($monthNumber * 18) + ($employeeIndex * 12);
                    $employee->payrollItems()->create([
                        'reference_month' => $month->toDateString(),
                        'event_type' => 'bonus',
                        'code' => 'BONUS',
                        'description' => 'Premiacao por metas do mes',
                        'earning' => $commission,
                    ]);
                    $net += $commission;
                }

                if (($monthNumber + $employeeIndex) % 3 === 0) {
                    $sundayAmount = 110 + ($employeeIndex * 10);
                    $employee->payrollItems()->create([
                        'reference_month' => $month->toDateString(),
                        'event_type' => 'sunday_work',
                        'worked_date' => $month->copy()->next(Carbon::SUNDAY)->toDateString(),
                        'paid_outside' => false,
                        'code' => 'DOM',
                        'description' => 'Plantao de domingo',
                        'earning' => $sundayAmount,
                    ]);
                    $net += $sundayAmount;
                }

                if (in_array($monthNumber, [11, 12], true)) {
                    $thirteenth = round((float) $employee->base_salary / 2, 2);
                    $employee->payrollItems()->create([
                        'reference_month' => $month->toDateString(),
                        'event_type' => 'thirteenth',
                        'code' => '13',
                        'description' => $monthNumber === 11 ? '13 salario - primeira parcela' : '13 salario - segunda parcela',
                        'earning' => $thirteenth,
                    ]);
                    $net += $thirteenth;
                }

                if ($monthNumber > 1 && $employeeIndex % 2 === 1 && $monthNumber % 2 === 0) {
                    $advance = 180 + ($employeeIndex * 20);
                    $employee->advances()->create([
                        'advance_date' => $month->copy()->subMonth()->day(22)->toDateString(),
                        'deduct_month' => $month->toDateString(),
                        'description' => 'Vale solicitado pelo funcionario',
                        'amount' => $advance,
                        'payment_method' => 'pix',
                    ]);
                    $net -= $advance;
                }

                $payrollTotal += max(0, $net);
            }

            $dueDate = $month->copy()->addMonthNoOverflow()->day(5);
            $paid = $monthNumber <= 10;
            $company->payables()->create([
                'financial_category_id' => $category->id,
                'description' => 'Folha funcionarios - '.$month->format('m/Y'),
                'amount' => round($payrollTotal, 2),
                'due_date' => $dueDate->toDateString(),
                'paid_at' => $paid ? $dueDate->toDateString() : null,
                'status' => $paid ? 'paid' : 'open',
                'source' => 'employee_recurring',
                'account_type' => 'boleto',
                'document_number' => 'FUNC-FOLHA-'.$month->format('Y-m'),
                'notes' => 'Folha consolidada ficticia da FARMA TESTE.',
            ]);
        }
    }

    private function createOperatingPayables(Company $company, array $categories, array $suppliers): void
    {
        $merchandiseSuppliers = [
            $suppliers['Drogaria Distribuidora Centro-Oeste'],
            $suppliers['MedFarma Distribuidora'],
            $suppliers['Saude Brasil Medicamentos'],
        ];

        foreach (self::MONTHLY_REVENUE as $monthNumber => $revenue) {
            $month = Carbon::create(2026, $monthNumber, 1);
            $paid = $monthNumber <= 10;
            $purchaseTotal = round($revenue * (0.505 + (($monthNumber % 3) * 0.006)), 2);
            $shares = [0.43, 0.34, 0.23];
            $allocated = 0.0;

            foreach ($merchandiseSuppliers as $index => $supplier) {
                $amount = $index === 2 ? round($purchaseTotal - $allocated, 2) : round($purchaseTotal * $shares[$index], 2);
                $allocated += $amount;
                $this->createPayable($company, $categories['Compra de mercadorias'], $supplier, 'Compra mensal de medicamentos - lote '.($index + 1), $amount, $month->copy()->day(8 + ($index * 7)), $paid, 'COMPRA-'.$month->format('Ym').'-'.($index + 1));
            }

            $this->createPayable($company, $categories['Aluguel'], $suppliers['Imobiliaria Avenida'], 'Aluguel da loja', 7800.00, $month->copy()->day(10), $paid, 'ALUG-'.$month->format('Ym'));
            $this->createPayable($company, $categories['Energia eletrica'], $suppliers['Energisa Demonstracao'], 'Energia eletrica', 2850 + ($monthNumber * 95), $month->copy()->day(18), $paid, 'ENER-'.$month->format('Ym'));
            $this->createPayable($company, $categories['Agua e saneamento'], $suppliers['Aguas Teste'], 'Agua e saneamento', 460 + ($monthNumber * 12), $month->copy()->day(20), $paid, 'AGUA-'.$month->format('Ym'));
            $this->createPayable($company, $categories['Internet e telefonia'], $suppliers['Conecta Telecom'], 'Internet, telefone e dados moveis', 689.90, $month->copy()->day(12), $paid, 'TEL-'.$month->format('Ym'));
            $this->createPayable($company, $categories['Servicos contabeis'], $suppliers['Contabil Mais'], 'Honorarios contabeis', 1850.00, $month->copy()->day(15), $paid, 'CONT-'.$month->format('Ym'));
            $this->createPayable($company, $categories['Impostos e taxas'], null, 'Impostos e taxas do periodo', round($revenue * 0.047, 2), $month->copy()->day(22), $paid, 'TRIB-'.$month->format('Ym'));
            $this->createPayable($company, $categories['Marketing'], $suppliers['Agencia Impulso Digital'], 'Gestao de marketing e criativos', 1650 + ($monthNumber * 70), $month->copy()->day(14), $paid, 'MKT-'.$month->format('Ym'));
            $this->createPayable($company, $categories['Manutencao'], $suppliers['Oficina das Farmacias'], 'Manutencao preventiva e pequenos reparos', 520 + (($monthNumber % 4) * 145), $month->copy()->day(25), $paid, 'MAN-'.$month->format('Ym'));
        }
    }

    private function createCreditCards(Company $company, array $categories): void
    {
        $cards = [
            $company->creditCards()->create(['name' => 'Nubank Empresarial', 'closing_day' => 25, 'due_day' => 5, 'is_active' => true]),
            $company->creditCards()->create(['name' => 'Banco do Brasil Corporativo', 'closing_day' => 28, 'due_day' => 10, 'is_active' => true]),
        ];

        foreach (self::MONTHLY_REVENUE as $monthNumber => $unused) {
            foreach ($cards as $cardIndex => $card) {
                $month = Carbon::create(2026, $monthNumber, 1);
                $dueDate = $month->copy()->day($card->due_day);
                $paid = $monthNumber <= 10;
                $items = $cardIndex === 0
                    ? [
                        ['Anuncios patrocinados', 980 + ($monthNumber * 45), 'Marketing'],
                        ['Sistema de gestao e licencas', 489.90, 'Sistemas e tecnologia'],
                        ['Materiais para loja', 320 + ($monthNumber * 18), 'Material de consumo'],
                    ]
                    : [
                        ['Combustivel das entregas', 760 + ($monthNumber * 32), 'Combustivel e entregas'],
                        ['Manutencao de equipamentos', 440 + (($monthNumber % 3) * 95), 'Manutencao'],
                        ['Telefonia do delivery', 219.90, 'Internet e telefonia'],
                    ];
                $total = round((float) collect($items)->sum(fn (array $item) => $item[1]), 2);
                $payable = $company->payables()->create([
                    'description' => 'Fatura '.$card->name.' - '.$month->format('m/Y'),
                    'amount' => $total,
                    'due_date' => $dueDate->toDateString(),
                    'paid_at' => $paid ? $dueDate->toDateString() : null,
                    'status' => $paid ? 'paid' : 'open',
                    'source' => 'credit_card_invoice',
                    'account_type' => 'cartao',
                    'document_number' => 'CARTAO-'.($cardIndex + 1).'-'.$month->format('Ym'),
                ]);
                $invoice = $company->creditCardInvoices()->create([
                    'payable_id' => $payable->id,
                    'credit_card_id' => $card->id,
                    'card_name' => $card->name,
                    'reference_month' => $month->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'total_amount' => $total,
                    'status' => $paid ? 'paid' : 'open',
                    'paid_at' => $paid ? $dueDate->toDateString() : null,
                    'notes' => 'Fatura ficticia para demonstracao por categorias.',
                ]);
                foreach ($items as [$description, $amount, $category]) {
                    $invoice->items()->create([
                        'financial_category_id' => $categories[$category]->id,
                        'description' => $description,
                        'amount' => $amount,
                        'is_recurring' => true,
                        'recurrence_start_month' => '2026-01-01',
                        'recurrence_end_month' => '2026-12-01',
                    ]);
                }
            }
        }
    }

    private function createProductsAndQuotations(Company $company, User $user, array $suppliers): void
    {
        $classes = ['SIMILARES', 'ETICOS', 'PERFUMARIA', 'SUPLEMENTOS E VITAM', 'GENERICOS', 'CONVENIENCIA', 'LEITES', 'HOSPITALAR', 'FRALDAS', 'SERVICOS'];
        foreach ($classes as $class) {
            $company->productClasses()->create(['name' => $class, 'is_active' => true]);
        }

        $products = [
            ['DIPIRONA 500MG C/20 CPR', 'GENERICOS', 7.80], ['LOSARTANA 50MG C/30 CPR', 'GENERICOS', 8.90],
            ['SINVASTATINA 20MG C/30 CPR', 'GENERICOS', 10.40], ['OMEPRAZOL 20MG C/28 CAPS', 'GENERICOS', 12.30],
            ['AMOXICILINA 500MG C/21 CAPS', 'GENERICOS', 24.80], ['DORFLEX C/36 CPR', 'ETICOS', 22.50],
            ['BENEGRIP MULTI C/12 CPR', 'ETICOS', 18.90], ['NEOSALDINA C/20 DRG', 'ETICOS', 17.40],
            ['LORATADINA 10MG C/12 CPR', 'SIMILARES', 9.60], ['NIMESULIDA 100MG C/12 CPR', 'SIMILARES', 8.20],
            ['VITAMINA C 1G C/10 EFER', 'SUPLEMENTOS E VITAM', 14.50], ['MULTIVITAMINICO A-Z C/60', 'SUPLEMENTOS E VITAM', 29.90],
            ['PROTETOR SOLAR FPS 50 120ML', 'PERFUMARIA', 42.80], ['SHAMPOO ANTICASPA 200ML', 'PERFUMARIA', 19.70],
            ['FRALDA ADULTO G C/8', 'FRALDAS', 31.60], ['FRALDA INFANTIL M C/24', 'FRALDAS', 36.90],
            ['LEITE INFANTIL 1 800G', 'LEITES', 54.30], ['COMPOSTO LACTEO 800G', 'LEITES', 39.80],
            ['SERINGA 5ML C/AGULHA', 'HOSPITALAR', 1.10], ['LUVA PROCEDIMENTO M C/100', 'HOSPITALAR', 27.90],
            ['AGUA MINERAL 500ML', 'CONVENIENCIA', 1.35], ['BARRA DE CEREAL 25G', 'CONVENIENCIA', 2.10],
            ['CURATIVO ADESIVO C/40', 'HOSPITALAR', 9.30], ['ALCOOL 70% 1L', 'HOSPITALAR', 8.70],
        ];
        $createdProducts = collect($products)->map(fn (array $product) => $company->products()->create([
            'description' => $product[0],
            'class' => $product[1],
            'last_purchase_price' => $product[2],
            'is_active' => true,
        ]));

        $finishedList = $company->purchaseLists()->create([
            'created_by' => $user->id,
            'title' => 'Reposicao semanal - demonstracao',
            'status' => 'finalized',
            'notes' => 'Lista ficticia com cotacao concluida.',
            'started_quotation_at' => '2026-10-20 09:00:00',
            'finalized_at' => '2026-10-21 16:30:00',
        ]);
        $listItems = $createdProducts->take(12)->values()->map(function ($product, int $index) use ($finishedList) {
            return $finishedList->items()->create([
                'product_id' => $product->id,
                'description' => $product->description,
                'quantity' => 4 + (($index % 5) * 2),
                'unit' => 'un',
            ]);
        });
        $quotation = $company->quotations()->create([
            'purchase_list_id' => $finishedList->id,
            'created_by' => $user->id,
            'status' => 'finalized',
            'quoted_at' => '2026-10-20 09:00:00',
            'finalized_at' => '2026-10-21 16:30:00',
        ]);
        $participants = collect([
            $suppliers['Drogaria Distribuidora Centro-Oeste'],
            $suppliers['MedFarma Distribuidora'],
            $suppliers['Saude Brasil Medicamentos'],
        ])->map(fn (Supplier $supplier) => $quotation->participants()->create([
            'supplier_id' => $supplier->id,
            'display_name' => $supplier->name,
        ]));
        foreach ($listItems as $itemIndex => $item) {
            $basePrice = (float) $item->product->last_purchase_price;
            $prices = [round($basePrice * 0.97, 2), round($basePrice * 1.04, 2), round($basePrice * 0.93, 2)];
            $winner = array_keys($prices, min($prices), true)[0];
            foreach ($participants as $participantIndex => $participant) {
                $quotation->prices()->create([
                    'purchase_list_item_id' => $item->id,
                    'quotation_supplier_id' => $participant->id,
                    'supplier_id' => $participant->supplier_id,
                    'unit_price' => $prices[$participantIndex] + (($itemIndex % 3) * 0.07),
                    'is_selected_winner' => $participantIndex === $winner,
                ]);
            }
        }

        $openList = $company->purchaseLists()->create([
            'created_by' => $user->id,
            'title' => 'Produtos em falta - lista aberta',
            'status' => 'open',
            'notes' => 'Lista pronta para o visitante testar inclusoes e edicoes.',
        ]);
        foreach ($createdProducts->slice(12, 8) as $index => $product) {
            $openList->items()->create([
                'product_id' => $product->id,
                'description' => $product->description,
                'quantity' => 2 + ($index % 4),
                'unit' => 'un',
            ]);
        }
    }

    private function createMarketingHistory(Company $company, User $user): void
    {
        $campaigns = [
            ['Conversoes WhatsApp - Ofertas', 1.00, 1.00, 'active'],
            ['Delivery - Raio 5 km', 0.82, 1.18, 'active'],
            ['Dermocosmeticos - Remarketing', 0.58, 1.42, 'active'],
            ['Institucional - Alcance Local', 0.34, 2.25, 'inactive'],
        ];

        foreach (self::MONTHLY_REVENUE as $monthNumber => $unused) {
            $month = Carbon::create(2026, $monthNumber, 1);
            $import = $company->marketingImports()->create([
                'user_id' => $user->id,
                'original_filename' => 'meta-ads-'.$month->format('Y-m').'.xlsx',
                'rows_created' => count($campaigns),
                'rows_updated' => 0,
                'rows_skipped' => 0,
                'period_start' => $month->toDateString(),
                'period_end' => $month->copy()->endOfMonth()->toDateString(),
            ]);

            foreach ($campaigns as $campaignIndex => [$name, $resultFactor, $costFactor, $status]) {
                $spent = round((620 + ($monthNumber * 42) + ($campaignIndex * 115)) * $costFactor, 2);
                $results = max(1, (int) round((95 + ($monthNumber * 9) - ($campaignIndex * 14)) * $resultFactor));
                $reach = (int) round(14500 + ($monthNumber * 1250) + ($campaignIndex * 2200));
                $impressions = (int) round($reach * (1.42 + ($campaignIndex * 0.13)));
                $linkClicks = max(1, (int) round($impressions * (0.0135 - ($campaignIndex * 0.0008))));
                $allClicks = $linkClicks + 120 + ($monthNumber * 8);
                $cpc = round($spent / $linkClicks, 6);
                $cpm = round(($spent / $impressions) * 1000, 6);
                $ctr = round(($linkClicks / $impressions) * 100, 6);
                $costPerResult = round($spent / $results, 6);
                $fingerprint = hash('sha256', implode('|', [$company->id, $month->format('Y-m'), $name]));

                $company->marketingCampaignMetrics()->create([
                    'marketing_import_id' => $import->id,
                    'fingerprint' => $fingerprint,
                    'report_start' => $month->toDateString(),
                    'report_end' => $month->copy()->endOfMonth()->toDateString(),
                    'campaign_name' => $name,
                    'delivery_status' => $monthNumber === 12 && $status === 'inactive' ? 'inactive' : $status,
                    'attribution_setting' => '7-day click or 1-day view',
                    'results' => $results,
                    'result_indicator' => 'Conversas iniciadas',
                    'reach' => $reach,
                    'impressions' => $impressions,
                    'link_clicks' => $linkClicks,
                    'all_clicks' => $allClicks,
                    'landing_page_views' => max(1, $linkClicks - 18),
                    'frequency' => round($impressions / $reach, 6),
                    'amount_spent' => $spent,
                    'cpc' => $cpc,
                    'cpm' => $cpm,
                    'ctr' => $ctr,
                    'cost_per_result' => $costPerResult,
                    'raw_data' => ['origem' => 'Dados ficticios FARMA TESTE', 'mes' => $month->format('m/Y')],
                ]);
            }
        }
    }

    private function createPayable(Company $company, FinancialCategory $category, ?Supplier $supplier, string $description, float $amount, Carbon $dueDate, bool $paid, string $document): void
    {
        $company->payables()->create([
            'supplier_id' => $supplier?->id,
            'financial_category_id' => $category->id,
            'description' => $description,
            'amount' => round($amount, 2),
            'due_date' => $dueDate->toDateString(),
            'paid_at' => $paid ? $dueDate->toDateString() : null,
            'status' => $paid ? 'paid' : 'open',
            'source' => 'demo_recurring',
            'account_type' => 'boleto',
            'document_number' => $document,
            'notes' => 'Lancamento ficticio do ambiente demonstrativo.',
        ]);
    }

    private function movementTypes(): array
    {
        return [
            ['code' => 'vale', 'name' => 'Vale / adiantamento', 'kind' => 'debit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => false, 'is_active' => true],
            ['code' => 'bonus', 'name' => 'Bonificacao', 'kind' => 'credit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => true, 'is_active' => true],
            ['code' => 'thirteenth', 'name' => '13 salario', 'kind' => 'credit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => true, 'is_active' => true],
            ['code' => 'vacation', 'name' => 'Ferias', 'kind' => 'credit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => true, 'is_active' => true],
            ['code' => 'sunday_work', 'name' => 'Trabalho domingo', 'kind' => 'credit', 'requires_worked_date' => true, 'allows_paid_outside' => true, 'is_taxable' => false, 'is_active' => true],
            ['code' => 'holiday_work', 'name' => 'Trabalho feriado', 'kind' => 'credit', 'requires_worked_date' => true, 'allows_paid_outside' => true, 'is_taxable' => false, 'is_active' => true],
            ['code' => 'discount', 'name' => 'Desconto / imposto', 'kind' => 'debit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => false, 'is_active' => true],
            ['code' => 'earning', 'name' => 'Outro acrescimo', 'kind' => 'credit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => true, 'is_active' => true],
        ];
    }

    private function salesWeight(Carbon $date): float
    {
        $weekdayFactor = match ($date->dayOfWeekIso) {
            1 => 0.92,
            2 => 0.96,
            3 => 1.02,
            4 => 1.08,
            5 => 1.18,
            6 => 1.30,
            default => 0.72,
        };
        $periodFactor = $date->day <= 10 ? 1.05 : ($date->day <= 20 ? 1.00 : 1.10);
        $wave = 1 + (sin(($date->day + ($date->month * 2)) * 1.37) * 0.055);

        return $weekdayFactor * $periodFactor * $wave;
    }

    private function weekdayName(Carbon $date): string
    {
        return [
            1 => 'segunda-feira',
            2 => 'terca-feira',
            3 => 'quarta-feira',
            4 => 'quinta-feira',
            5 => 'sexta-feira',
            6 => 'sabado',
            7 => 'domingo',
        ][$date->dayOfWeekIso];
    }
}
