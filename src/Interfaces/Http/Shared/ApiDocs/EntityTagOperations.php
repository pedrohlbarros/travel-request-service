<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\ApiDocs;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Interfaces\Http\Shared\Concurrency\EntityTag;
use Interfaces\Http\Shared\Concurrency\RespondsWithEntityTag;
use Interfaces\Http\Shared\Middleware\RequireIfMatch;
use Interfaces\Http\Shared\Problem\ProblemType;

/**
 * Adds what Scramble cannot infer about optimistic concurrency: the If-Match a write needs, its 428, and the ETag
 * a successful response carries.
 */
final class EntityTagOperations extends OperationExtension
{
    private const string HEADER_LOCATION = 'header';

    private const string ENTITY_TAG_EXAMPLE = '"3"';

    private const int FIRST_SUCCESS_STATUS = 200;

    private const int FIRST_REDIRECT_STATUS = 300;

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $isConditionalWrite = \in_array(RequireIfMatch::class, $routeInfo->route->gatherMiddleware(), true);
        $hasEntityTag = $routeInfo->reflectionMethod()
            ?->getAttributes(RespondsWithEntityTag::class) !== [];

        if ($isConditionalWrite) {
            $this->requireIfMatch(operation: $operation);
        }

        if ($hasEntityTag) {
            $this->documentEntityTag(operation: $operation);
        }
    }

    private function requireIfMatch(Operation $operation): void
    {
        $ifMatch = new Parameter(EntityTag::IF_MATCH_HEADER, self::HEADER_LOCATION);
        $ifMatch->required(true);
        $textSchema = ProblemDetailsResponses::schemaOf(type: new StringType());
        $ifMatch->setSchema($textSchema);
        $ifMatch->description('ETag of the version the change is based on. Anything else than the current one fails with 412.');
        $ifMatch->example(self::ENTITY_TAG_EXAMPLE);
        $operation->addParameters([$ifMatch]);

        $preconditionRequired = new ProblemType(
            status: 428,
            type: '/problems/precondition-required',
            title: 'The request must be conditional.',
        );
        $operation->addResponse(ProblemDetailsResponses::responseFor(problemType: $preconditionRequired));
    }

    private function documentEntityTag(Operation $operation): void
    {
        $entityTag = new Header();
        $entityTag->setDescription('Current version of the resource, for the If-Match of the next write.');
        $textSchema = ProblemDetailsResponses::schemaOf(type: new StringType());
        $entityTag->setSchema($textSchema);
        $entityTag->setExample(self::ENTITY_TAG_EXAMPLE);

        foreach ($operation->responses ?? [] as $response) {
            if (! $response instanceof Response) {
                continue;
            }

            $isSuccess = \is_int($response->code)
                && $response->code >= self::FIRST_SUCCESS_STATUS
                && $response->code < self::FIRST_REDIRECT_STATUS;

            if ($isSuccess) {
                $response->addHeader(EntityTag::HEADER, $entityTag);
            }
        }
    }
}
