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
                'description' => 'API de commandes B2B en PHP natif, sans framework ni dependance d execution.',
            ],
            'servers' => [['url' => '/']],
            'paths' => [
                '/health' => [
                    'get' => [
                        'summary' => 'Verifier la disponibilite de l API',
                        'responses' => [
                            '200' => [
                                'description' => 'API disponible',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Health']]],
                            ],
                        ],
                    ],
                ],
                '/idempotency-key' => [
                    'get' => [
                        'summary' => 'Generer une cle d idempotence aleatoire',
                        'responses' => [
                            '200' => [
                                'description' => 'Cle cryptographiquement aleatoire',
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
                        'summary' => 'Lister les produits actifs',
                        'parameters' => [
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Produits actifs et indicateur de page suivante',
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
                            '422' => ['description' => 'Parametres invalides', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Base de donnees indisponible', 'content' => ['application/json' => ['schema' => $error]]],
                        ],
                    ],
                ],
                '/products/{id}' => [
                    'get' => [
                        'summary' => 'Consulter un produit actif',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Produit',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => ['data' => $product],
                                ]]],
                            ],
                            '404' => ['description' => 'Produit introuvable', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Base de donnees indisponible', 'content' => ['application/json' => ['schema' => $error]]],
                        ],
                    ],
                ],
                '/orders' => [
                    'get' => [
                        'summary' => 'Lister les commandes recentes',
                        'parameters' => [
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Commandes recentes et indicateur de page suivante',
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
                            '422' => ['description' => 'Parametres invalides', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Base de donnees indisponible', 'content' => ['application/json' => ['schema' => $error]]],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Creer une commande et reserver le stock',
                        'description' => 'Rejouer la meme cle et le meme contenu retourne la commande existante sans reserver a nouveau. Une meme cle avec un contenu different retourne 409.',
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
                                'description' => 'Commande creee ou deja creee pour cette cle',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => ['data' => $order],
                                ]]],
                            ],
                            '400' => ['description' => 'JSON invalide ou cle idempotente manquante', 'content' => ['application/json' => ['schema' => $error]]],
                            '404' => ['description' => 'Produit introuvable', 'content' => ['application/json' => ['schema' => $error]]],
                            '409' => ['description' => 'Stock insuffisant ou cle reutilisee avec un autre contenu', 'content' => ['application/json' => ['schema' => $error]]],
                            '415' => ['description' => 'Content-Type non supporte', 'content' => ['application/json' => ['schema' => $error]]],
                            '422' => ['description' => 'Commande invalide ou produit indisponible', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Base de donnees indisponible', 'content' => ['application/json' => ['schema' => $error]]],
                        ],
                    ],
                ],
                '/orders/{id}' => [
                    'get' => [
                        'summary' => 'Consulter une commande',
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Commande et lignes avec prix factures',
                                'content' => ['application/json' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => ['data' => $order],
                                ]]],
                            ],
                            '404' => ['description' => 'Commande introuvable', 'content' => ['application/json' => ['schema' => $error]]],
                            '503' => ['description' => 'Base de donnees indisponible', 'content' => ['application/json' => ['schema' => $error]]],
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
