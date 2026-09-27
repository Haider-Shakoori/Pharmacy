<?php

namespace App\Services\Purchasing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class PurchaseTotalsCalculator
{
    public function calculate(array $lines): array
    {
        $subtotal = BigDecimal::zero();
        $discount = BigDecimal::zero();
        $landed = BigDecimal::zero();

        foreach ($lines as $line) {
            $quantity = BigDecimal::of((string) $line['ordered_quantity']);
            $unitCost = BigDecimal::of((string) $line['unit_cost']);
            $lineDiscount = BigDecimal::of((string) ($line['discount_amount'] ?? '0'));
            $lineLanded = BigDecimal::of((string) ($line['landed_cost_allocated'] ?? '0'));
            $base = $quantity->multipliedBy($unitCost);

            if ($lineDiscount->isGreaterThan($base)) {
                throw new InvalidArgumentException('Line discount cannot exceed the line base amount.');
            }

            $subtotal = $subtotal->plus($base);
            $discount = $discount->plus($lineDiscount);
            $landed = $landed->plus($lineLanded);
        }

        return [
            'subtotal' => $this->scale($subtotal),
            'discount_total' => $this->scale($discount),
            'landed_cost_total' => $this->scale($landed),
            'grand_total' => $this->scale($subtotal->minus($discount)->plus($landed)),
        ];
    }

    public function lineTotal(array $line): string
    {
        $total = BigDecimal::of((string) $line['ordered_quantity'])
            ->multipliedBy((string) $line['unit_cost'])
            ->minus((string) ($line['discount_amount'] ?? '0'))
            ->plus((string) ($line['landed_cost_allocated'] ?? '0'));

        if ($total->isNegative()) {
            throw new InvalidArgumentException('Line total cannot be negative.');
        }

        return $this->scale($total);
    }

    private function scale(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
