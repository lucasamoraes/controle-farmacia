<?php

namespace App\Services;

class PayrollCalculator
{
    public function calculate(float $grossSalary): array
    {
        $inss = $this->inss($grossSalary);
        $fgtsBase = $grossSalary;
        $fgts = round($fgtsBase * 0.08, 2);
        $legalIrrfBase = max(0, $grossSalary - $inss);
        $simplifiedDeduction = 607.20;
        $irrfBase = min($legalIrrfBase, max(0, $grossSalary - $simplifiedDeduction));
        [$irrfRate, $irrfDeduction] = $this->irrfBracket($irrfBase);
        $irrf = max(0, round(($irrfBase * $irrfRate) - $irrfDeduction, 2));

        return [
            'base_salary' => round($grossSalary, 2),
            'inss_salary' => round($grossSalary, 2),
            'inss_discount' => $inss,
            'fgts_base' => round($fgtsBase, 2),
            'fgts_month' => $fgts,
            'irrf_base' => round($irrfBase, 2),
            'irrf_bracket' => $irrfRate * 100,
            'irrf_discount' => $irrf,
        ];
    }

    private function inss(float $salary): float
    {
        $brackets = [
            [1621.00, 0.075],
            [2902.84, 0.09],
            [4354.27, 0.12],
            [8475.55, 0.14],
        ];
        $previous = 0.0;
        $total = 0.0;

        foreach ($brackets as [$limit, $rate]) {
            if ($salary <= $previous) {
                break;
            }

            $base = min($salary, $limit) - $previous;
            $total += $base * $rate;
            $previous = $limit;
        }

        return round($total, 2);
    }

    private function irrfBracket(float $base): array
    {
        return match (true) {
            $base <= 2428.80 => [0.0, 0.0],
            $base <= 2826.65 => [0.075, 182.16],
            $base <= 3751.05 => [0.15, 394.16],
            $base <= 4664.68 => [0.225, 675.49],
            default => [0.275, 908.73],
        };
    }
}
