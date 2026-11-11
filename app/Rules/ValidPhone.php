<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Teléfono completo y con código de país real: lo que GHL y WhatsApp aceptan. Ver Phone::problem. */
class ValidPhone implements ValidationRule
{
    public function __construct(private readonly string $prefix = '') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $problem = Phone::problem((string) $value);
        if ($problem !== null) {
            $fail($this->prefix.$problem);
        }
    }
}
