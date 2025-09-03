<?php declare(strict_types=1);

namespace App\Service;

class A
{
    public function entry(): void
    {
        $b = new B();
        $b->work();
    }

    protected function helper(): void
    {
        $c = new C();
        $c->assist();
    }
}
