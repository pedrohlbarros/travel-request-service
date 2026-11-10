<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\NoFrameworkFunctionsInCoreRule;

/**
 * @extends RuleTestCase<NoFrameworkFunctionsInCoreRule>
 */
final class NoFrameworkFunctionsInCoreRuleTest extends RuleTestCase
{
    public function test_ReportsFrameworkFunctionsInDomain(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/src/Domain/CallsFrameworkFunctions.php'], [
            ['Domain and Application must not call the framework function now(); inject a port instead.', 11],
            ['Domain and Application must not call the framework function config(); inject a port instead.', 12],
            ['Domain and Application must not call the framework function collect(); inject a port instead.', 13],
        ]);
    }

    public function test_ReportsFrameworkFunctionsInApplication(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/src/Application/CallsFrameworkFunctions.php'], [
            ['Domain and Application must not call the framework function app(); inject a port instead.', 11],
        ]);
    }

    public function test_AllowsNativeAndOwnFunctionsInCore(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/src/Domain/CallsAllowedFunctions.php'], []);
    }

    public function test_AllowsFrameworkFunctionsInAdapters(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/src/Infrastructure/CallsFrameworkFunctions.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoFrameworkFunctionsInCoreRule(self::createReflectionProvider());
    }
}
