<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Deptrac only sees helper calls that are fully qualified or imported;
 * PHPStan resolves the real function, so `now()` inside a namespace is caught too.
 *
 * @implements Rule<FuncCall>
 */
final readonly class NoFrameworkFunctionsInCoreRule implements Rule
{
    private const string CORE_PATH_PATTERN = '#/src/(Domain|Application)/#';

    private const string FRAMEWORK_PATH_PATTERN = '#/vendor/(laravel|illuminate)/#';

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name || preg_match(
            self::CORE_PATH_PATTERN,
            $scope->getFile(),
        ) !== 1) {
            return [];
        }

        if (!$this->reflectionProvider->hasFunction(
            $node->name,
            $scope,
        )) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction(
            $node->name,
            $scope,
        );
        $definedIn = $function->getFileName();
        $resolved = $definedIn === null ? false : realpath($definedIn);

        if ($definedIn === null) {
            return [];
        }

        $isFrameworkFunction = preg_match(
            self::FRAMEWORK_PATH_PATTERN,
            $resolved === false ? $definedIn : $resolved,
        ) === 1;

        if (! $isFrameworkFunction) {
            return [];
        }

        $message = sprintf(
            'Domain and Application must not call the framework function %s(); inject a port instead.',
            $function->getName(),
        );

        return [
            RuleErrorBuilder::message($message)
                ->identifier('architecture.frameworkFunction')
                ->build(),
        ];
    }
}
