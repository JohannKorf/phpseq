<?php declare(strict_types=1);

namespace App\Service;

class A
{
    private B $b;
    public function __construct(B $b)
    {
        $this->b = $b;
    }

    public function entry(): void
    {
        $this->b->work();
        (new C())->assist();
    }
}
