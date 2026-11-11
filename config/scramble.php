<?php

declare(strict_types=1);

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Interfaces\Http\Shared\ApiDocs\EntityTagOperations;
use Interfaces\Http\Shared\ApiDocs\ProblemDetailsResponses;

return [
    'api_path' => 'api/v1',

    'api_domain' => null,

    'export_path' => 'storage/openapi.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'openapi_version' => OpenApiVersion::V3_1,

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => 'Corporate travel requests: create, list, edit and decide, with JWT authentication. '
            . 'Errors are RFC 9457 problem details; writes need the ETag of the resource in If-Match.',
    ],

    'ui' => [
        'title' => 'Travel Request Service API',
    ],

    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', false),
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    // Without a gate defined, the docs only open with APP_ENV=local.
    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [
        ProblemDetailsResponses::class,
        EntityTagOperations::class,
    ],

    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
