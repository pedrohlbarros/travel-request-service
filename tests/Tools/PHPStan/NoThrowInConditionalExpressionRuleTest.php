<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\NoThrowInConditionalExpressionRule;

/**
 * @extends RuleTestCase<NoThrowInConditionalExpressionRule>
 */
final class NoThrowInConditionalExpressionRuleTest extends RuleTestCase
{
    public function test_ReportsThrowsInsideExpressionsAndNestedTernaries(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ExplicitFlow/ConditionalThrows.php'],
            [
                ['Do not throw from `??`; compare with null in an if and throw there.', 13],
                ['Do not throw from a ternary; use an if.', 18],
                ['Do not nest ternaries; use if or match.', 23],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new NoThrowInConditionalExpressionRule();
    }
}
