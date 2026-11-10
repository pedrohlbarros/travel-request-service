<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A method called on the result of another call starts on its own line: `Http::timeout(...)` then `->get(...)` below.
 * A single call on a variable or property (`$this->users->get(...)`) stays on one line.
 *
 * @implements Rule<Node\Expr>
 */
final readonly class OneChainedCallPerLineRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall && ! $node instanceof NullsafeMethodCall) {
            return [];
        }

        if (! $this->isCallResult(target: $node->var) || $node->name->getStartLine() > $node->var->getEndLine()) {
            return [];
        }

        $message = \sprintf(
            'Start ->%s on its own line; it is chained on another call.',
            CallSyntax::displayName(call: $node),
        );

        return [
            RuleErrorBuilder::message(message: $message)
                ->identifier(identifier: 'explicitFlow.chainOnOneLine')
                ->build(),
        ];
    }

    private function isCallResult(Node\Expr $target): bool
    {
        return $target instanceof CallLike
            && ($target instanceof MethodCall || $target instanceof NullsafeMethodCall || $target instanceof StaticCall || $target instanceof FuncCall);
    }
}
