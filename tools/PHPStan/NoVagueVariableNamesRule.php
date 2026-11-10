<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Names must say what the value is: no one/two-letter names and no generic
 * placeholders. Only declarations are checked, so each name is reported once.
 *
 * @implements Rule<Node>
 */
final readonly class NoVagueVariableNamesRule implements Rule
{
    private const int MINIMUM_NAME_LENGTH = 3;

    private const array ALLOWED_SHORT_NAMES = ['i', 'id', 'ip'];

    private const array VAGUE_NAMES = [
        'arr', 'bar', 'baz', 'data', 'foo', 'info', 'obj', 'res', 'str', 'stuff', 'temp', 'thing', 'tmp', 'val', 'var',
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->declaredNames($node) as $declaredName) {
            $error = $this->errorFor($declaredName, $node->getStartLine());

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function declaredNames(Node $node): array
    {
        return match (true) {
            $node instanceof Param => $this->namesIn($node->var),
            $node instanceof Assign, $node instanceof AssignRef => $this->namesIn($node->var),
            $node instanceof Foreach_ => [...$this->namesIn($node->keyVar), ...$this->namesIn($node->valueVar)],
            $node instanceof Catch_ => $this->namesIn($node->var),
            $node instanceof Property => array_values(array_map(
                static fn(Node\PropertyItem $property): string => $property->name->toString(),
                $node->props,
            )),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function namesIn(?Node $target): array
    {
        if ($target instanceof Variable && \is_string($target->name)) {
            return [$target->name];
        }

        if ($target instanceof List_) {
            $names = [];

            foreach ($target->items as $listItem) {
                $names = [...$names, ...$this->namesIn($listItem?->value)];
            }

            return $names;
        }

        return [];
    }

    private function errorFor(string $name, int $line): ?IdentifierRuleError
    {
        $normalizedName = strtolower($name);

        if ($normalizedName === 'this' || \in_array($normalizedName, self::ALLOWED_SHORT_NAMES, true)) {
            return null;
        }

        if (\in_array($normalizedName, self::VAGUE_NAMES, true)) {
            return RuleErrorBuilder::message(sprintf('Name $%s is too generic; say what the value is.', $name))
                ->identifier('naming.vague')
                ->line($line)
                ->build();
        }

        if (mb_strlen($name) < self::MINIMUM_NAME_LENGTH) {
            return RuleErrorBuilder::message(sprintf('Name $%s is too short; say what the value is.', $name))
                ->identifier('naming.tooShort')
                ->line($line)
                ->build();
        }

        return null;
    }
}
