<?php declare(strict_types=1);

namespace PhpSeq\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class MethodCallCollector extends NodeVisitorAbstract
{
    private string $className;
    private ?string $methodName = null;
    private string $visibility = 'public';
    private array $calls = [];
    private array $methods = [];

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->methodName = $node->name->toString();
            if ($node->isPublic()) {
                $this->visibility = 'public';
            } elseif ($node->isProtected()) {
                $this->visibility = 'protected';
            } else {
                $this->visibility = 'private';
            }
            $this->methods[$this->methodName] = $this->visibility;
        }

        if ($node instanceof Node\Expr\MethodCall && $this->methodName) {
            $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
            if ($name) {
                $this->calls[] = [
                    'from' => $this->className . '::' . $this->methodName,
                    'to' => $name,
                ];
            }
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->methodName = null;
        }
    }

    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }
}
