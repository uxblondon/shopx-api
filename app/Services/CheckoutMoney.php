<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class CheckoutMoney
{
    public static function pence($value): int
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 999999.99) {
            throw ValidationException::withMessages(['payment.amount' => 'The checkout amount is invalid.']);
        }
        $pence = (int) round((float) $value * 100);
        if (abs((float) $value * 100 - $pence) > 0.00001) {
            throw ValidationException::withMessages(['payment.amount' => 'Prices must use whole pence.']);
        }
        return $pence;
    }

    public static function pounds(int $pence): string
    {
        return intdiv($pence, 100).'.'.str_pad((string) ($pence % 100), 2, '0', STR_PAD_LEFT);
    }
}
