<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\ApiDocs;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as SchemaObject;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as SchemaType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\ValidationException;
use Interfaces\Http\Shared\Problem\ProblemDetailsRenderer;
use Interfaces\Http\Shared\Problem\ProblemType;
use Interfaces\Http\Shared\Problem\ProblemTypeRegistry;

/**
 * Documents every error the API knows as the RFC 9457 body the renderer really sends, read from the same
 * registry, so the docs cannot drift from the responses.
 */
final class ProblemDetailsResponses extends ExceptionToResponseExtension
{
    private const string COMPONENT_GROUP = 'responses';

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $this->problemTypeOf(exceptionClass: $type->name) !== null;
    }

    public function toResponse(Type $type): ?Response
    {
        $exceptionClass = $type instanceof ObjectType ? $type->name : '';
        $problemType = $this->problemTypeOf(exceptionClass: $exceptionClass);

        if ($problemType === null) {
            return null;
        }

        // Only request validation knows which field failed; a domain rule reports in the detail.
        $hasFieldErrors = is_a($exceptionClass, ValidationException::class, true);

        return self::responseFor(problemType: $problemType, hasFieldErrors: $hasFieldErrors);
    }

    public static function schemaOf(SchemaType $type): Schema
    {
        $schema = Schema::fromType($type);
        \assert($schema instanceof Schema);

        return $schema;
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference(self::COMPONENT_GROUP, $type->name, $this->components);
    }

    public static function responseFor(ProblemType $problemType, bool $hasFieldErrors = false): Response
    {
        $body = new SchemaObject();
        $body->addProperty('type', new StringType()->const($problemType->type));
        $body->addProperty('title', new StringType()->const($problemType->title));
        $body->addProperty('status', new IntegerType()->const($problemType->status));
        $body->addProperty('detail', new StringType());
        $body->addProperty('instance', new StringType());
        $body->addProperty('request_id', new StringType());
        $body->addProperty('trace_id', new StringType()->nullable(true));
        $requiredFields = ['type', 'title', 'status', 'detail', 'instance', 'request_id', 'trace_id'];

        if ($hasFieldErrors) {
            $fieldErrors = new SchemaObject();
            $messages = new ArrayType();
            $messages->setItems(new StringType());
            $fieldErrors->additionalProperties($messages);
            $body->addProperty('errors', $fieldErrors->setDescription('Messages per invalid field.'));
            $requiredFields[] = 'errors';
        }

        $body->setRequired($requiredFields);

        return Response::make($problemType->status)
            ->setDescription($problemType->title)
            ->setContent(ProblemDetailsRenderer::CONTENT_TYPE, self::schemaOf(type: $body));
    }

    private function problemTypeOf(string $exceptionClass): ?ProblemType
    {
        if (! class_exists($exceptionClass)) {
            return null;
        }

        if (is_a($exceptionClass, ValidationException::class, true)) {
            return new ProblemType(
                status: 422,
                type: ProblemDetailsRenderer::VALIDATION_FAILED_TYPE,
                title: 'The request is invalid.',
            );
        }

        return app(ProblemTypeRegistry::class)
            ->findForClass(exceptionClass: $exceptionClass);
    }
}
