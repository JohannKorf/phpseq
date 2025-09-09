<?php declare(strict_types=1);

namespace PhpSeq\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class MethodCallCollector extends NodeVisitorAbstract
{
    private string $namespace = '';
    private string $className;
    /** @var array<string,string> */
    private array $uses = [];

    private ?string $methodName = null;
    /** @var array<string,string> */
    private array $varTypes = [];
    /** @var array<string,string> */
    private array $propertyTypes = [];

    /** @var array<string,string> */
    private array $methods = [];
    /** @var array<int,array{from:string,to:string}> */
    private array $calls = [];

    public function __construct(string $className) { $this->className = $className; }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = $node->name ? $node->name->toString() : '';
            $this->uses = [];
        }
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $u) {
                $alias = $u->alias ? $u->alias->toString() : $u->name->getLast();
                $this->uses[$alias] = $u->name->toString();
            }
        }
        if ($node instanceof Node\Stmt\Class_) {
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Property) {
                    $t = $stmt->type;
                    if ($t instanceof Node\Name) {
                        $this->propertyTypes[$stmt->props[0]->name->toString()] = $this->resolveFqn($t->toString());
                    }
                }
                if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === '__construct') {
                    foreach ($stmt->params as $param) {
                        if (($param->flags & (Node\Stmt\Class_::MODIFIER_PUBLIC|Node\Stmt\Class_::MODIFIER_PROTECTED|Node\Stmt\Class_::MODIFIER_PRIVATE)) !== 0) {
                            if ($param->type instanceof Node\Name && $param->var instanceof Node\Expr\Variable) {
                                $this->propertyTypes[$param->var->name] = $this->resolveFqn($param->type->toString());
                            }
                        }
                    }
                }
            }
        }
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->methodName = $node->name->toString();
            $vis = $node->isPublic() ? 'public' : ($node->isProtected() ? 'protected' : 'private');
            $this->methods[$this->className . '::' . $this->methodName] = $vis;
            $this->varTypes = [];
            foreach ($this->propertyTypes as $prop => $fqn) {
                $this->varTypes['this->' . $prop] = $fqn;
            }
        }

        // $x = new Foo
        if ($node instanceof Node\Expr\Assign && $node->expr instanceof Node\Expr\New_) {
            $key = $this->varKey($node->var);
            if ($key && $node->expr->class instanceof Node\Name) {
                $fqn = $this->resolveFqn($node->expr->class->toString());
                $this->varTypes[$key] = $fqn;
                if ($this->methodName) $this->calls[] = ['from' => $this->className . '::' . $this->methodName, 'to' => $fqn . '::__construct'];
            }
        }
        // bare new Foo()
        if ($node instanceof Node\Expr\New_ && $this->methodName && $node->class instanceof Node\Name) {
            $fqn = $this->resolveFqn($node->class->toString());
            $this->calls[] = ['from' => $this->className . '::' . $this->methodName, 'to' => $fqn . '::__construct'];
        }
        // (new Foo())->bar()
        if ($node instanceof Node\Expr\MethodCall && $node->var instanceof Node\Expr\New_ && $this->methodName) {
            $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
            if ($name && $node->var->class instanceof Node\Name) {
                $fqn = $this->resolveFqn($node->var->class->toString());
                $this->calls[] = ['from' => $this->className . '::' . $this->methodName, 'to' => $fqn . '::' . $name];
            }
        }
        // $obj->bar()
        if ($node instanceof Node\Expr\MethodCall && $this->methodName) {
            $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
            $key = $this->varKey($node->var);
            if ($name && $key && isset($this->varTypes[$key])) {
                $this->calls[] = ['from' => $this->className . '::' . $this->methodName, 'to' => $this->varTypes[$key] . '::' . $name];
            }
        }
        // Static Foo::bar()
        if ($node instanceof Node\Expr\StaticCall && $this->methodName) {
            $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
            if ($name && $node->class instanceof Node\Name) {
                $fqn = $this->resolveFqn($node->class->toString());
                $this->calls[] = ['from' => $this->className . '::' . $this->methodName, 'to' => $fqn . '::' . $name];
            }
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->methodName = null;
            $this->varTypes = [];
        }
    }

    private function varKey($var): ?string
    {
        if ($var instanceof Node\Expr\Variable && is_string($var->name)) return $var->name;
        if ($var instanceof Node\Expr\PropertyFetch && $var->var instanceof Node\Expr\Variable
            && $var->var->name === 'this' && $var->name instanceof Node\Identifier) {
            return 'this->' . $var->name->toString();
        }
        return null;
    }

    private function resolveFqn(string $name): string
    {
        if ($name[0] === '\\') return ltrim($name, '\\');
        if (isset($this->uses[$name])) return $this->uses[$name];
        if (str_contains($name, '\\')) return $name;
        return ($this->namespace ? $this->namespace . '\\' : '') . $name;
    }

    /** @return array<string,string> */
    public function getMethods(): array { return $this->methods; }
    /** @return array<int,array{from:string,to:string}> */
    public function getCalls(): array { return $this->calls; }
}
