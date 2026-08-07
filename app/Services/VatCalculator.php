<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Prices in this shop are VAT inclusive, so VAT is extracted from the gross the customer pays
 * rather than added on top.
 */
class VatCalculator
{
    public function rate(): int
    {
        return max(0, (int) config('shop.vat.rate'));
    }

    public function vatOn(int $gross): int
    {
        $rate = $this->rate();

        return $rate === 0 ? 0 : (int) round($gross * $rate / (100 + $rate));
    }

    /**
     * Share the payable gross across the lines in proportion to their list prices, then split each
     * share into net and VAT. The shares are allocated by largest remainder so they sum to exactly
     * the payable gross, which is what keeps an invoice's lines adding up to its total.
     *
     * @param  list<int>  $listPrices  gross price per line, before any discount
     * @param  int  $payableGross  what the customer actually pays, after discount
     * @return list<VatLine>
     */
    public function allocate(array $listPrices, int $payableGross): array
    {
        $listTotal = array_sum($listPrices);

        if ($listPrices === [] || $listTotal <= 0) {
            return array_map(fn (): VatLine => new VatLine(0, 0, 0), $listPrices);
        }

        $shares = [];
        $remainders = [];

        foreach ($listPrices as $index => $price) {
            $exact = $price * $payableGross / $listTotal;
            $shares[$index] = (int) floor($exact);
            $remainders[$index] = $exact - $shares[$index];
        }

        $leftover = $payableGross - array_sum($shares);

        arsort($remainders);

        foreach (array_keys($remainders) as $index) {
            if ($leftover <= 0) {
                break;
            }

            $shares[$index]++;
            $leftover--;
        }

        ksort($shares);

        return array_map(function (int $gross): VatLine {
            $vat = $this->vatOn($gross);

            return new VatLine($gross, $gross - $vat, $vat);
        }, array_values($shares));
    }
}
