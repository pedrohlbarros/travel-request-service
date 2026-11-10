<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\NamedArgumentsRule;

/**
 * @extends RuleTestCase<NamedArgumentsRule>
 */
final class NamedArgumentsRuleTest extends RuleTestCase
{
    public function test_ReportsPositionalArgumentsToOwnCodeAndTheSchemaBuilder(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ExplicitFlow/PositionalArguments.php'],
            [
                ['Argument #1 of new self must be named: label.', 19],
                ['Argument #1 of make() must be named: label.', 24],
                ['Argument #1 of describe() must be named: prefix.', 26],
                ['Argument #1 of string() must be named: column.', 41],
                ['Argument #2 of string() must be named: length.', 41],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new NamedArgumentsRule(reflectionProvider: self::createReflectionProvider());
    }
}
