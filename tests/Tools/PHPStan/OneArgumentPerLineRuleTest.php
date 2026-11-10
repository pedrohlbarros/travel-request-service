<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\OneArgumentPerLineRule;

/**
 * @extends RuleTestCase<OneArgumentPerLineRule>
 */
final class OneArgumentPerLineRuleTest extends RuleTestCase
{
    public function test_ReportsCallsWhoseArgumentsShareALine(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ExplicitFlow/ArgumentLines.php'],
            [
                ['Put each of the 3 arguments of str_replace() on its own line.', 11],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new OneArgumentPerLineRule();
    }
}
