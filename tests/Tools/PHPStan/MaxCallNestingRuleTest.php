<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\MaxCallNestingRule;

/**
 * @extends RuleTestCase<MaxCallNestingRule>
 */
final class MaxCallNestingRuleTest extends RuleTestCase
{
    public function test_ReportsOnlyCallsNestedThreeLevelsDeep(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ExplicitFlow/NestedCalls.php'],
            [
                ['trim() nests calls 3 levels deep; give the inner value a named variable.', 11],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new MaxCallNestingRule();
    }
}
