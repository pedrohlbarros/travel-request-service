<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Calls to this project's own classes name every argument. Framework, libraries, PHPUnit and PHP itself are left
 * alone: their parameter names are not ours to keep stable, and forwarded magic methods break with named arguments.
 * Spread and variadic arguments stay positional, as PHP requires.
 *
 * @implements Rule<CallLike>
 */
final readonly class NamedArgumentsRule implements Rule
{
    private const array OWN_NAMESPACES = ['Domain\\', 'Application\\', 'Infrastructure\\', 'Interfaces\\', 'Tests\\', 'Tools\\'];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $arguments = CallSyntax::argumentsOf(call: $node);
        $hasPositionalArgument = array_any(
            $arguments,
            static fn(Arg $argument): bool => $argument->name === null && ! $argument->unpack,
        );

        if (! $hasPositionalArgument) {
            return [];
        }

        $callee = $this->ownCalleeOf(
            call: $node,
            scope: $scope,
        );

        if ($callee === null) {
            return [];
        }

        return $this->errorsFor(
            call: $node,
            arguments: $arguments,
            parameters: $callee->getVariants()[0]->getParameters(),
        );
    }

    /**
     * @param list<Arg> $arguments
     * @param list<ParameterReflection> $parameters
     *
     * @return list<IdentifierRuleError>
     */
    private function errorsFor(CallLike $call, array $arguments, array $parameters): array
    {
        if ($this->hasSpreadOrVariadicArgument(
            arguments: $arguments,
            parameters: $parameters,
        )) {
            return [];
        }

        $errors = [];

        foreach ($arguments as $position => $argument) {
            $parameter = $parameters[$position] ?? null;

            if ($argument->name !== null || $parameter === null) {
                continue;
            }

            $message = \sprintf(
                'Argument #%d of %s must be named: %s.',
                $position + 1,
                CallSyntax::displayName(call: $call),
                $parameter->getName(),
            );
            $errors[] = RuleErrorBuilder::message($message)
                ->identifier('explicitFlow.positionalArgument')
                ->line($argument->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * PHP forbids a named argument before a spread or a positional variadic one, so such calls stay positional.
     *
     * @param list<Arg> $arguments
     * @param list<ParameterReflection> $parameters
     */
    private function hasSpreadOrVariadicArgument(array $arguments, array $parameters): bool
    {
        $lastParameter = $parameters === [] ? null : $parameters[array_key_last($parameters)];

        foreach ($arguments as $position => $argument) {
            $parameter = $parameters[$position] ?? $lastParameter;

            if ($argument->unpack || ($argument->name === null && $parameter?->isVariadic() === true)) {
                return true;
            }
        }

        return false;
    }

    private function ownCalleeOf(CallLike $call, Scope $scope): ?ExtendedMethodReflection
    {
        $callee = match (true) {
            $call instanceof MethodCall, $call instanceof NullsafeMethodCall => $this->method(
                typeWithMethod: $scope->getType($call->var),
                methodName: $call->name,
                scope: $scope,
            ),
            $call instanceof StaticCall && $call->class instanceof Name => $this->staticMethod(
                call: $call,
                scope: $scope,
            ),
            $call instanceof New_ && $call->class instanceof Name => $this->constructor(className: $scope->resolveName($call->class)),
            default => null,
        };

        if ($callee === null || ! $this->isOwnCode(className: $callee->getDeclaringClass()
            ->getName())) {
            return null;
        }

        return $callee;
    }

    private function staticMethod(StaticCall $call, Scope $scope): ?ExtendedMethodReflection
    {
        \assert($call->class instanceof Name);
        $classType = new ObjectType($scope->resolveName($call->class));

        return $this->method(
            typeWithMethod: $classType,
            methodName: $call->name,
            scope: $scope,
        );
    }

    private function method(Type $typeWithMethod, Node $methodName, Scope $scope): ?ExtendedMethodReflection
    {
        if (! $methodName instanceof Node\Identifier) {
            return null;
        }

        return $scope->getMethodReflection(
            $typeWithMethod,
            $methodName->toString(),
        );
    }

    private function constructor(string $className): ?ExtendedMethodReflection
    {
        if (! $this->reflectionProvider->hasClass($className)) {
            return null;
        }

        $class = $this->reflectionProvider->getClass($className);

        return $class->hasConstructor() ? $class->getConstructor() : null;
    }

    private function isOwnCode(string $className): bool
    {
        return array_any(
            self::OWN_NAMESPACES,
            static fn(string $namespace): bool => str_starts_with(
                $className,
                $namespace,
            ),
        );
    }
}
