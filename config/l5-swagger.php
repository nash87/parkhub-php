<?php

use L5Swagger\Generator;

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'ParkHub API',
            ],
            'routes' => [
                /*
                 * Route for accessing api documentation interface
                 */
                'api' => 'api/documentation',
            ],
            'paths' => [
                /*
                 * Edit to set the api's base path
                 */
                'base' => env('L5_SWAGGER_BASE_PATH', '/'),

                /*
                 * Absolute paths to directory containing the swagger annotations are stored.
                 */
                'annotations' => [
                    base_path('app'),
                ],

                /*
                 * Absolute path to directory where to export views
                 */
                'views' => base_path('resources/views/vendor/l5-swagger'),

                /*
                 * Edit to set the swagger storage path for auto generating
                 */
                'docs' => storage_path('api-docs'),

                /*
                 * Edit to set path where swagger ui assets should be stored
                 */
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),

                /*
                 * Absolute path to directories that you would like to exclude from swagger generation
                 */
                'excludes' => [],
            ],

            'scanOptions' => [
                'open_api_spec_version' => env('L5_SWAGGER_OPEN_API_SPEC_VERSION', Generator::OPEN_API_DEFAULT_SPEC_VERSION ?? '3.0'),
                'analyser' => null,
                'analysis' => null,
                'processors' => [],
                'pattern' => null,
                'exclude' => [],
                'default_processors_configuration' => [],
            ],

            'securityDefinitions' => [
                'securitySchemes' => [
                    'sanctum' => [
                        'type' => 'http',
                        'description' => 'Laravel Sanctum token authentication',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
                'security' => [],
            ],

            'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
            'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
            'proxy' => false,
            'additional_config_url' => null,
            'operations_sort' => null,
            'validator_url' => null,
            'ui' => [
                'display' => [
                    'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                    'filter' => env('L5_SWAGGER_UI_FILTERS', true),
                    'show_extensions' => env('L5_SWAGGER_UI_SHOW_EXTENSIONS', false),
                    'show_common_extensions' => env('L5_SWAGGER_UI_SHOW_COMMON_EXTENSIONS', false),
                    'try_it_out_enabled' => env('L5_SWAGGER_UI_TRY_IT_OUT_ENABLED', false),
                    'request_snippets_enabled' => env('L5_SWAGGER_UI_REQUEST_SNIPPETS_ENABLED', false),
                ],
                'authorization' => [
                    'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', false),
                    'oauth2' => [
                        'use_pkce_with_authorization_code_grant' => false,
                    ],
                ],
            ],
        ],
    ],
    'defaults' => [
        'routes' => [
            'docs' => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],
            'group_options' => [],
        ],
        'paths' => [
            'docs_json' => 'api-docs.json',
            'docs_yaml' => 'api-docs.yaml',
            'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
            'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
        ],
        'scanOptions' => [
            'open_api_spec_version' => env('L5_SWAGGER_OPEN_API_SPEC_VERSION', '3.0'),
        ],
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
        'proxy' => false,
        'additional_config_url' => null,
        'operations_sort' => null,
        'validator_url' => null,
        'ui' => [
            'display' => [
                'doc_expansion' => 'none',
                'filter' => true,
                'show_extensions' => false,
                'show_common_extensions' => false,
                'try_it_out_enabled' => false,
                'request_snippets_enabled' => false,
            ],
            'authorization' => [
                'persist_authorization' => false,
                'oauth2' => [
                    'use_pkce_with_authorization_code_grant' => false,
                ],
            ],
        ],
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost'),
        ],
    ],
];
