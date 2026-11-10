<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A call inside a call inside a call has to be read inside out. Two levels are allowed; deeper values get a named
 * variable. A method chain is not nesting: `$a->b()->c()` is one level.
 *
 * @implements Rule<CallLike>
 */
final readonly class MaxCallNestingRule implements Rule
{
    public const int DEEPEST_NESTING = 2;

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->deepestCallAmongArgumentsOf(call: $node) !== self::DEEPEST_NESTING) {
            return [];
        }

        $message = \sprintf(
            '%s nests calls %d levels deep; give the inner value a named variable.',
            CallSyntax::displayName(call: $node),
            self::DEEPEST_NESTING + 1,
        );

        return [
            RuleErrorBuilder::message(message: $message)
                ->identifier(identifier: 'explicitFlow.nestedCalls')
                ->build(),
        ];
    }

    private function deepestCallAmongArgumentsOf(CallLike $call): int
    {
        $deepest = 0;

        foreach (CallSyntax::argumentsOf(call: $call) as $argument) {
            $deepest = max(
                $deepest,
                $this->callDepthOf(node: $argument->value),
            );
        }

        return $deepest;
    }

    private function callDepthOf(Node $node): int
    {
        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            return 0;
        }

        if ($node instanceof CallLike) {
            return max(
                1 + $this->deepestCallAmongArgumentsOf(call: $node),
                $this->depthOfChainedTarget(call: $node),
            );
        }

        return $this->deepestChildDepth(node: $node);
    }

    private function depthOfChainedTarget(CallLike $call): int
    {
        return match (true) {
            $call instanceof MethodCall, $call instanceof NullsafeMethodCall => $this->callDepthOf(node: $call->var),
            $call instanceof StaticCall && $call->class instanceof Node\Expr => $this->callDepthOf(node: $call->class),
            default => 0,
        };
    }

    private function deepestChildDepth(Node $node): int
    {
        $deepest = 0;

        foreach (get_object_vars($node) as $children) {
            foreach (\is_array($children) ? $children : [$children] as $child) {
                if ($child instanceof Node) {
                    $deepest = max(
                        $deepest,
                        $this->callDepthOf(node: $child),
                    );
                }
            }
        }

        return $deepest;
    }
}
