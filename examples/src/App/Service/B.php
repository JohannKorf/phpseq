<?php declare(strict_types=1);

namespace App\Service;

class B
{
    public function work(): void
    {
        $c = new C();
        $c->assist();
    }

    private function secret(): void
    {
    }
}
