<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\OneChainedCallPerLineRule;

/**
 * @extends RuleTestCase<OneChainedCallPerLineRule>
 */
final class OneChainedCallPerLineRuleTest extends RuleTestCase
{
    public function test_ReportsACallChainedOnAnotherCallOnTheSameLine(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ExplicitFlow/ChainedCalls.php'],
            [
                ['Start ->second() on its own line; it is chained on another call.', 13],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new OneChainedCallPerLineRule();
    }
}
