<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\ApiDocs;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Interfaces\Http\Shared\ApiDocs\ProblemDetailsResponses;
use stdClass;
use Tests\Support\LaravelTestCase;

final class ProblemDetailsResponsesTest extends LaravelTestCase
{
    private const string MISSING_CLASS = 'Interfaces\Http\Shared\NoSuchException';

    public function test_LeavesClassesThatDoNotExistToScramble(): void
    {
        $extension = $this->extension();

        $isHandled = $extension->shouldHandle(type: new ObjectType(self::MISSING_CLASS));

        self::assertFalse($isHandled);
    }

    public function test_DocumentsNothingForAClassWithoutAProblemType(): void
    {
        $extension = $this->extension();

        $response = $extension->toResponse(type: new ObjectType(stdClass::class));

        self::assertNull($response);
    }

    private function extension(): ProblemDetailsResponses
    {
        $infer = $this->app->make(Infer::class);
        $context = new OpenApiContext(new OpenApi(), new GeneratorConfig());

        return new ProblemDetailsResponses(
            $infer,
            new TypeTransformer($infer, $context),
            $context->openApi->components,
        );
    }
}
