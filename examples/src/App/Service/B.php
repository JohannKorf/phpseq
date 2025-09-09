<?php declare(strict_types=1);

namespace App\Service;

class B
{
    public function work(): void
    {
        C::staticAssist();
        $c = new C();
        $c->assist();
    }
}
