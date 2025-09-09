<?php declare(strict_types=1);

namespace App\Requests\GuestCart;

use App\Rules\WhoisNameserversRule;

class AddGuestCartItemRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nameservers' => [new WhoisNameserversRule()],
        ];
    }
}
