<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A throw hidden at the end of `??` or inside a ternary reads like a value; an explicit `if` says it is control flow.
 * Nested ternaries are banned for the same reason.
 *
 * @implements Rule<Node\Expr>
 */
final readonly class NoThrowInConditionalExpressionRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return match (true) {
            $node instanceof Coalesce => $this->coalesceErrors(coalesce: $node),
            $node instanceof Ternary => $this->ternaryErrors(ternary: $node),
            default => [],
        };
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function coalesceErrors(Coalesce $coalesce): array
    {
        if (! $coalesce->right instanceof Throw_) {
            return [];
        }

        return [$this->error(
            message: 'Do not throw from `??`; compare with null in an if and throw there.',
            identifier: 'explicitFlow.throwInCoalesce',
        )];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function ternaryErrors(Ternary $ternary): array
    {
        $errors = [];

        if ($ternary->if instanceof Throw_ || $ternary->else instanceof Throw_) {
            $errors[] = $this->error(
                message: 'Do not throw from a ternary; use an if.',
                identifier: 'explicitFlow.throwInTernary',
            );
        }

        if ($ternary->cond instanceof Ternary || $ternary->if instanceof Ternary || $ternary->else instanceof Ternary) {
            $errors[] = $this->error(
                message: 'Do not nest ternaries; use if or match.',
                identifier: 'explicitFlow.nestedTernary',
            );
        }

        return $errors;
    }

    private function error(string $message, string $identifier): IdentifierRuleError
    {
        return RuleErrorBuilder::message(message: $message)
            ->identifier(identifier: $identifier)
            ->build();
    }
}
