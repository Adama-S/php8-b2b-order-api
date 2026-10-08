<?php

declare(strict_types=1);

namespace App\Documentation;

final class OpenApi
{
    public static function specification(): array
    {
        $error = ['$ref' => '#/components/schemas/Error'];
        $product = ['$ref' => '#/components/schemas/Product'];
        $order = ['$ref' => '#/components/schemas/Order'];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'B2B Order API',
                'version' => '1.0.0',
                'description' => 'A native PHP B2B order API with no framework or runtime dependencies.',
            ],
            'servers' => [['url' => '/']],
            'paths' => [
                '/health' => [
                    'get' => [
                        'summary' => 'Check API availability',
                        'responses' => [
                            '200' => [
                                'description' => 'API is available',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Health']]],
                            ],
                        ],
                    ],
                ],
                '/idempotency-key' => [
                    'get' => [
                        'summary' => 'Generate a random idempotency key',
                        'responses' => [
                            '200' => [
                                'description' => 'Cryptographically random key',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'required' => ['idempotency_key'],
                                    'properties' => ['idempotency_key' => ['type' => 'string', 'minLength' => 69, 'maxLength' => 69]],
                                ]]],
                            ],
                        ],
                    ],
                ],
                '/products' => [
                    'get' => [
                        'summary' => 'List active products',
                        'parameters' => [
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Active products and next-page indicator',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'required' => ['data', 'pagination'],
                                    'properties' => [
                                        'data' => ['type' => 'array', 'items' => $product],
                                        'pagination' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'page' => ['type' => 'integer'],
                                                'per_page' => ['type' => 'integer'],
                                                'has_more' => ['type' => 'boolean'],
                                            ],
                                        ],
                                    ],
                                ]]],
                            ],
                            '422' => ['description' => 'Invalid pagination parameters', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Database unavailable', 'content' => ['application/json' => ['schema' => $error]]],
                        ],
                    ],
                ],
                '/products/{id}' => [
                    'get' => [
                        'summary' => 'Get an active product',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Product details',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => ['data' => $product],
                                ]]],
                            ],
                            '404' => ['description' => 'Product not found', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Database unavailable', 'content' => ['application/json' => ['schema' => $error]]],
                        ],
                    ],
                ],
                '/orders' => [
                    'get' => [
                        'summary' => 'List recent orders',
                        'parameters' => [
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Recent orders and next-page indicator',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'required' => ['data', 'pagination'],
                                    'properties' => [
                                        'data' => ['type' => 'array', 'items' => $order],
                                        'pagination' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'page' => ['type' => 'integer'],
                                                'per_page' => ['type' => 'integer'],
                                                'has_more' => ['type' => 'boolean'],
                                            ],
                                        ],
                                    ],
                                ]]],
                            ],
                            '422' => ['description' => 'Invalid pagination parameters', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Database unavailable', 'content' => ['application/json' => ['schema' => $error]]],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Create an order and reserve stock',
                        'description' => 'Replaying the same key with the same content returns the existing order without reserving stock again. Reusing a key with different content returns 409.',
                        'parameters' => [
                            [
                                'name' => 'Idempotency-Key',
                                'in' => 'header',
                                'required' => true,
                                'schema' => ['type' => 'string', 'maxLength' => 255],
                            ],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['customer_id', 'items'],
                                        'properties' => [
                                            'customer_id' => ['type' => 'integer', 'minimum' => 1],
                                            'items' => [
                                                'type' => 'array',
                                                'minItems' => 1,
                                                'items' => [
                                                    'type' => 'object',
                                                    'required' => ['product_id', 'quantity'],
                                                    'properties' => [
                                                        'product_id' => ['type' => 'integer', 'minimum' => 1],
                                                        'quantity' => ['type' => 'integer', 'minimum' => 1],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Order created or already created for this key',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => ['data' => $order],
                                ]]],
                            ],
                            '400' => ['description' => 'Invalid JSON or missing idempotency key', 'content' => ['application/json' => ['schema' => $error]]],
                            '404' => ['description' => 'Product not found', 'content' => ['application/json' => ['schema' => $error]]],
                            '409' => ['description' => 'Insufficient stock or key reused with different content', 'content' => ['application/json' => ['schema' => $error]]],
                            '415' => ['description' => 'Unsupported content type', 'content' => ['application/json' => ['schema' => $error]]],
                            '422' => ['description' => 'Invalid order or unavailable product', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Database unavailable', 'content' => ['application/json' => ['schema' => $error]]],
                        ],
                    ],
                ],
                '/orders/{id}' => [
                    'get' => [
                        'summary' => 'Get an order',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Order and line items with their recorded prices',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => ['data' => $order],
                                ]]],
                            ],
                            '404' => ['description' => 'Order not found', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Database unavailable', 'content' => ['application/json' => ['schema' => $error]]],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'required' => ['error', 'message'],
                        'properties' => [
                            'error' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                        ],
                    ],
                    'Health' => [
                        'type' => 'object',
                        'required' => ['status'],
                        'properties' => ['status' => ['type' => 'string', 'const' => 'ok']],
                    ],
                    'Product' => [
                        'type' => 'object',
                        'required' => ['id', 'reference', 'name', 'price_cents', 'stock_quantity'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'reference' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'price_cents' => ['type' => 'integer', 'minimum' => 0],
                            'stock_quantity' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                    'OrderItem' => [
                        'type' => 'object',
                        'required' => ['product_id', 'quantity', 'unit_price_cents'],
                        'properties' => [
                            'product_id' => ['type' => 'integer'],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                            'unit_price_cents' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                    'Order' => [
                        'type' => 'object',
                        'required' => ['id', 'customer_id', 'status', 'total_cents', 'created_at', 'items'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'customer_id' => ['type' => 'integer'],
                            'status' => ['type' => 'string', 'const' => 'accepted'],
                            'total_cents' => ['type' => 'integer', 'minimum' => 0],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                            'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrderItem']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
