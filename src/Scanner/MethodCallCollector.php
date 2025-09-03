<?php declare(strict_types=1);

namespace PhpSeq\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class MethodCallCollector extends NodeVisitorAbstract
{
    /** @var array<int, array{fromClass:?string, fromMethod:?string, fromVisibility:?string, type:string, caller:?string, method:string}> */
    private array $calls = [];

    private ?string $currentClass = null;
    private ?string $currentMethod = null;
    private ?string $currentVisibility = null;

    public function enterNode(Node $node)
    {
        // Track current class
        if ($node instanceof Node\Stmt\Class_ && $node->name) {
            $this->currentClass = $node->name->name;
        }

        // Track current method + visibility
        if ($node instanceof Node\Stmt\ClassMethod && $node->name) {
            $this->currentMethod = $node->name->name;
            $this->currentVisibility = $this->getVisibility($node);
        }

        // Instance method call
        if ($node instanceof Node\Expr\MethodCall) {
            $caller = $this->getNodeName($node->var);
            $method = $this->getNodeName($node->name);

            if ($method) {
                $this->calls[] = [
                    'fromClass'      => $this->currentClass,
                    'fromMethod'     => $this->currentMethod,
                    'fromVisibility' => $this->currentVisibility,
                    'type'           => 'method',
                    'caller'         => $caller,
                    'method'         => $method,
                ];
            }
        }

        // Static call
        if ($node instanceof Node\Expr\StaticCall) {
            $caller = $this->getNodeName($node->class);
            $method = $this->getNodeName($node->name);

            if ($caller && $method) {
                $this->calls[] = [
                    'fromClass'      => $this->currentClass,
                    'fromMethod'     => $this->currentMethod,
                    'fromVisibility' => $this->currentVisibility,
                    'type'           => 'static',
                    'caller'         => $caller,
                    'method'         => $method,
                ];
            }
        }

        // Function call
        if ($node instanceof Node\Expr\FuncCall) {
            $func = $this->getNodeName($node->name);

            if ($func) {
                $this->calls[] = [
                    'fromClass'      => $this->currentClass,
                    'fromMethod'     => $this->currentMethod,
                    'fromVisibility' => $this->currentVisibility,
                    'type'           => 'function',
                    'caller'         => null,
                    'method'         => $func,
                ];
            }
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->currentMethod = null;
            $this->currentVisibility = null;
        }

        if ($node instanceof Node\Stmt\Class_) {
            $this->currentClass = null;
        }
    }

    /**
     * Extract visibility string from a class method.
     */
    private function getVisibility(Node\Stmt\ClassMethod $node): string
    {
        if ($node->isPublic()) {
            return 'public';
        }
        if ($node->isProtected()) {
            return 'protected';
        }
        if ($node->isPrivate()) {
            return 'private';
        }
        return 'public'; // default
    }

    /**
     * Extract a readable name from identifiers, names, or variables.
     */
    private function getNodeName($node): ?string
    {
        if ($node instanceof Node\Identifier) {
            return $node->name;
        }

        if ($node instanceof Node\Name) {
            return $node->toString();
        }

        if ($node instanceof Node\Expr\Variable) {
            return is_string($node->name) ? '$' . $node->name : null;
        }

        return null;
    }

    /**
     * @return array<int, array{fromClass:?string, fromMethod:?string, fromVisibility:?string, type:string, caller:?string, method:string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
