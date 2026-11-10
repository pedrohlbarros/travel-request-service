<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\ArgumentLayoutRule;

/**
 * @extends RuleTestCase<ArgumentLayoutRule>
 */
final class ArgumentLayoutRuleTest extends RuleTestCase
{
    public function test_SplitsLongOrCrowdedCallsAndJoinsShortOnes(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/ExplicitFlow/ArgumentLayout.php'],
            [
                ['Put each of the 4 arguments of four() on its own line: 4+ arguments or over 120 columns.', 11],
                ['Keep the arguments of str_replace() on one line; it fits in 120 columns.', 27],
                ['Put each of the 3 arguments of str_replace() on its own line: 4+ arguments or over 120 columns.', 40],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new ArgumentLayoutRule();
    }
}
