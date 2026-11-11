<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Concurrency;

use Attribute;

/**
 * Marks an action whose successful response carries the ETag of the resource, so the API docs can say so.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class RespondsWithEntityTag {}
