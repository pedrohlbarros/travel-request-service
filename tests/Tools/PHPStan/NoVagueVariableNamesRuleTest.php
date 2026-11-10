<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\NoVagueVariableNamesRule;

/**
 * @extends RuleTestCase<NoVagueVariableNamesRule>
 */
final class NoVagueVariableNamesRuleTest extends RuleTestCase
{
    public function test_ReportsShortAndGenericNamesWhereTheyAreDeclared(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/Naming/VagueNames.php'], [
            ['Name $tmp is too generic; say what the value is.', 11],
            ['Name $x is too short; say what the value is.', 13],
            ['Name $ab is too short; say what the value is.', 15],
            ['Name $k is too short; say what the value is.', 17],
            ['Name $v is too short; say what the value is.', 17],
            ['Name $e is too short; say what the value is.', 22],
            ['Name $a is too short; say what the value is.', 25],
            ['Name $data is too generic; say what the value is.', 25],
            ['Name $res is too generic; say what the value is.', 27],
        ]);
    }

    public function test_AcceptsDescriptiveAndAllowedShortNames(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/Naming/DescriptiveNames.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoVagueVariableNamesRule();
    }
}
