<?php declare(strict_types=1);

namespace App\Rules;

class WhoisNameserversRule
{
    public function __construct()
    {
    }

    public function passes($attribute, $value): bool
    {
        return true;
    }
}
