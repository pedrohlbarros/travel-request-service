<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Config;

use Illuminate\Config\Repository;
use Infrastructure\Shared\Config\TypedConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TypedConfigTest extends TestCase
{
    private const string KEY = 'service.setting';

    public function test_ReadsANonEmptyString(): void
    {
        $config = new Repository(['service' => ['setting' => 'value']]);

        $value = TypedConfig::nonEmptyString(config: $config, key: self::KEY);

        self::assertSame('value', $value);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notNonEmptyStrings(): array
    {
        return [
            'empty' => [''],
            'missing' => [null],
            'number' => [5],
        ];
    }

    #[DataProvider('notNonEmptyStrings')]
    public function test_RejectsWhatIsNotANonEmptyString(mixed $setting): void
    {
        $config = new Repository(['service' => ['setting' => $setting]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Config "service.setting" must be a non-empty string.');

        TypedConfig::nonEmptyString(config: $config, key: self::KEY);
    }

    public function test_ReadsAPositiveIntegerFromNumericText(): void
    {
        $config = new Repository(['service' => ['setting' => '30']]);

        $value = TypedConfig::positiveInt(config: $config, key: self::KEY);

        self::assertSame(30, $value);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notPositiveIntegers(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'text' => ['soon'],
            'missing' => [null],
        ];
    }

    #[DataProvider('notPositiveIntegers')]
    public function test_RejectsWhatIsNotAPositiveInteger(mixed $setting): void
    {
        $config = new Repository(['service' => ['setting' => $setting]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Config "service.setting" must be a positive number.');

        TypedConfig::positiveInt(config: $config, key: self::KEY);
    }
}
