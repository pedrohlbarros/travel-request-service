<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;

/**
 * Where the parts of a call are in the source, shared by the call formatting rules.
 */
final readonly class CallSyntax
{
    /**
     * @return list<Arg>
     */
    public static function argumentsOf(CallLike $call): array
    {
        if ($call->isFirstClassCallable()) {
            return [];
        }

        $arguments = array_filter(
            $call->getRawArgs(),
            static fn(Node $argument): bool => $argument instanceof Arg,
        );

        return array_values($arguments);
    }

    /**
     * The line where the argument list opens: the end of the callee's name.
     */
    public static function openingLine(CallLike $call): int
    {
        $callee = match (true) {
            $call instanceof MethodCall, $call instanceof NullsafeMethodCall, $call instanceof StaticCall => $call->name,
            $call instanceof FuncCall => $call->name,
            $call instanceof New_ => $call->class,
            default => $call,
        };

        return $callee->getEndLine();
    }

    public static function displayName(CallLike $call): string
    {
        return match (true) {
            $call instanceof MethodCall, $call instanceof NullsafeMethodCall, $call instanceof StaticCall => self::identifierOf(name: $call->name) . '()',
            $call instanceof FuncCall => self::identifierOf(name: $call->name) . '()',
            $call instanceof New_ => 'new ' . self::identifierOf(name: $call->class),
            default => 'call',
        };
    }

    private static function identifierOf(Node $name): string
    {
        return match (true) {
            $name instanceof Node\Identifier, $name instanceof Node\Name => $name->toString(),
            default => '{expression}',
        };
    }
}
