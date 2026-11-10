<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * With two or more arguments, each goes on its own line, so calls do not run off to the right.
 *
 * @implements Rule<CallLike>
 */
final readonly class OneArgumentPerLineRule implements Rule
{
    private const int FEWEST_ARGUMENTS_TO_SPLIT = 2;

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $arguments = CallSyntax::argumentsOf(call: $node);

        if (\count($arguments) < self::FEWEST_ARGUMENTS_TO_SPLIT || $this->isOneArgumentPerLine(
            call: $node,
            arguments: $arguments,
        )) {
            return [];
        }

        $message = \sprintf(
            'Put each of the %d arguments of %s on its own line.',
            \count($arguments),
            CallSyntax::displayName(call: $node),
        );

        return [
            RuleErrorBuilder::message(message: $message)
                ->identifier(identifier: 'explicitFlow.argumentsOnOneLine')
                ->line(line: $arguments[0]->getStartLine())
                ->build(),
        ];
    }

    /**
     * @param list<Node\Arg> $arguments
     */
    private function isOneArgumentPerLine(CallLike $call, array $arguments): bool
    {
        $previousLine = CallSyntax::openingLine(call: $call);

        foreach ($arguments as $argument) {
            if ($argument->getStartLine() <= $previousLine) {
                return false;
            }

            $previousLine = $argument->getEndLine();
        }

        return true;
    }
}
