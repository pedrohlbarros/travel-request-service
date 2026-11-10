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
    public function test_ReportsPositionalArgumentsToOwnCodeOnly(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ExplicitFlow/PositionalArguments.php'],
            [
                ['Argument #1 of new self must be named: label.', 18],
                ['Argument #1 of make() must be named: label.', 23],
                ['Argument #1 of describe() must be named: prefix.', 25],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new NamedArgumentsRule(reflectionProvider: self::createReflectionProvider());
    }
}
