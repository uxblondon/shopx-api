<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AccountPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) < 15 || strlen($value) > 72 || str_contains($value, "\0")) {
            $fail('Use at least 15 characters and no more than 72 bytes for your password.');
        }
    }
}
