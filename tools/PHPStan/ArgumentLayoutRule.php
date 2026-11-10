<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A call goes one argument per line when it has many arguments, or two or more that would not fit the line;
 * otherwise it stays on one line, so the same call never shows up in two shapes. A single argument is never split
 * out, and an argument that spans lines by itself (closure, multiline array) exempts the call from the one-line form.
 *
 * @implements Rule<CallLike>
 */
final readonly class ArgumentLayoutRule implements Rule
{
    public const int LONGEST_LINE = 120;

    public const int FEWEST_ARGUMENTS_TO_SPLIT = 4;

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $arguments = CallSyntax::argumentsOf(call: $node);

        if ($arguments === []) {
            return [];
        }

        $source = SourceText::of(file: $this->fileOf(scope: $scope));
        $isSplitRequired = \count($arguments) >= self::FEWEST_ARGUMENTS_TO_SPLIT || (
            \count($arguments) > 1
            && $this->oneLineWidth(call: $node, arguments: $arguments, source: $source) > self::LONGEST_LINE
        );

        if ($isSplitRequired) {
            return $this->splitErrors(call: $node, arguments: $arguments);
        }

        return $this->oneLineErrors(call: $node, arguments: $arguments);
    }

    /**
     * @param list<Arg> $arguments
     *
     * @return list<IdentifierRuleError>
     */
    private function splitErrors(CallLike $call, array $arguments): array
    {
        if ($this->isOneArgumentPerLine(call: $call, arguments: $arguments)) {
            return [];
        }

        $message = \sprintf(
            'Put each of the %d arguments of %s on its own line: %d+ arguments or over %d columns.',
            \count($arguments),
            CallSyntax::displayName(call: $call),
            self::FEWEST_ARGUMENTS_TO_SPLIT,
            self::LONGEST_LINE,
        );

        return [$this->error(
            message: $message,
            identifier: 'explicitFlow.argumentsNotSplit',
            line: $arguments[0]->getStartLine(),
        )];
    }

    /**
     * @param list<Arg> $arguments
     *
     * @return list<IdentifierRuleError>
     */
    private function oneLineErrors(CallLike $call, array $arguments): array
    {
        $hasMultilineArgument = array_any($arguments, fn(Arg $argument): bool => $this->isMultiline(node: $argument));
        $lastArgument = end($arguments);
        $isOnOneLine = $lastArgument !== false && $lastArgument->getEndLine() === CallSyntax::openingLine(call: $call);

        if ($hasMultilineArgument || $isOnOneLine) {
            return [];
        }

        $message = \sprintf(
            'Keep the arguments of %s on one line; it fits in %d columns.',
            CallSyntax::displayName(call: $call),
            self::LONGEST_LINE,
        );

        return [$this->error(
            message: $message,
            identifier: 'explicitFlow.argumentsNotJoined',
            line: $arguments[0]->getStartLine(),
        )];
    }

    /**
     * Width of the line holding the call once its arguments are joined on it.
     *
     * @param list<Arg> $arguments
     */
    private function oneLineWidth(CallLike $call, array $arguments, string $source): int
    {
        $calleeEnd = CallSyntax::calleeEndPosition(call: $call);
        $openingParenthesis = (int) strpos($source, '(', $calleeEnd);
        $textBeforeParenthesis = substr($source, 0, $openingParenthesis);
        $previousLineBreak = strrpos($textBeforeParenthesis, "\n");
        $lineStart = $previousLineBreak === false ? 0 : $previousLineBreak + 1;
        $prefix = substr($source, $lineStart, $openingParenthesis - $lineStart + 1);
        $argumentTexts = array_map(
            static fn(Arg $argument): string => self::collapsedTextOf(argument: $argument, source: $source),
            $arguments,
        );
        $joinedArguments = implode(', ', $argumentTexts);
        $lineEnd = strpos($source, "\n", $call->getEndFilePos());
        $suffix = substr(
            $source,
            $call->getEndFilePos() + 1,
            ($lineEnd === false ? \strlen($source) : $lineEnd) - $call->getEndFilePos() - 1,
        );

        return mb_strlen($prefix . $joinedArguments . ')' . $suffix);
    }

    private static function collapsedTextOf(Arg $argument, string $source): string
    {
        $argumentLength = $argument->getEndFilePos() - $argument->getStartFilePos() + 1;
        $text = substr($source, $argument->getStartFilePos(), $argumentLength);

        return (string) preg_replace('/\s+/', ' ', $text);
    }

    /**
     * Code from a trait is analysed in the context of each class using it, but its positions are in the trait's file.
     */
    private function fileOf(Scope $scope): string
    {
        $trait = $scope->isInTrait() ? $scope->getTraitReflection() : null;
        $traitFile = $trait?->getFileName();

        return \is_string($traitFile) ? $traitFile : $scope->getFile();
    }

    private function isMultiline(Node $node): bool
    {
        return $node->getStartLine() !== $node->getEndLine();
    }

    /**
     * @param list<Arg> $arguments
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

    private function error(string $message, string $identifier, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier($identifier)
            ->line($line)
            ->build();
    }
}
