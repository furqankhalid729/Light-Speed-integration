<?php

namespace App\Utils;

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Shopify\Clients\Graphql;

class ShopifyProductUpdate
{
    public function inventoryUpdate($sku, $quantity)
    {
        $shop = env("STORE_URL");
        $graphqlEndpoint = "https://$shop/admin/api/2021-10/graphql.json";
        $inventoryData = $this->getInventoryId($sku);
        $query = <<<QUERY
            mutation inventorySetOnHandQuantities(\$input: InventorySetOnHandQuantitiesInput!) {
                inventorySetOnHandQuantities(input: \$input) {
                userErrors {
                    field
                    message
                }
                inventoryAdjustmentGroup {
                    createdAt
                    reason
                    referenceDocumentUri
                    changes {
                    name
                    delta
                    }
                }
                }
            }
        QUERY;

        $variables = [
            "input" => [

                "reason" => "correction",
                "referenceDocumentUri" => "logistics://some.warehouse/take/2023-01-23T13:14:15Z",
                "setQuantities" => [
                    [
                        "inventoryItemId" => $inventoryData["inventoryItemId"],
                        "locationId" => "gid://shopify/Location/77625721073",
                        "quantity" => $quantity,
                    ]
                ],
            ],
        ];
        $client = new Graphql(env("STORE_URL"), env("ACCESS_TOKEN"));
        $response = $client->query(["query" => $query, "variables" => $variables]);
        $data = json_decode($response->getBody(), true);
        Log::info('DAta of update graphql- ', ['graph' => $data, "quantity"=> $quantity]);
        return $data;
    }

    function updatePrice($sku, $price)
    {
        $variantData = $this->getInventoryId($sku);
        $query = <<<GRAPHQL
            mutation productVariantUpdate(\$input: ProductVariantInput!) {
            productVariantUpdate(input: \$input) {
                productVariant {
                id
                price
                sku
                }
                userErrors {
                field
                message
                }
            }
            }
        GRAPHQL;
        $variables = [
            "input" => [
                "id" => $variantData["variantId"],
                "price" => $price,
            ],
        ];
        $client = new Graphql(env("STORE_URL"), env("ACCESS_TOKEN"));
        $response = $client->query(["query" => $query, "variables" => $variables]);
        $data = json_decode($response->getBody(), true);
        Log::info('Price udapte Graph- ', ['graph' => $data]);
        return $data;
    }

    function getInventoryId($sku)
    {
        $shop = env("STORE_URL");
        $graphqlEndpoint = "https://$shop/admin/api/2021-10/graphql.json";
        $query = <<<GRAPHQL
        {
            productVariants(first: 1, query: "sku:$sku") {
                edges {
                node {
                    id
                    inventoryItem {
                    id
                    inventoryLevels(first: 1) {
                        edges {
                        node {
                            id
                            available
                            incoming
                            location {
                            id
                            name
                            }
                        }
                        }
                    }
                    }
                }
                }
            }
        }
        GRAPHQL;
        $client = new Client();
        $response = $client->post($graphqlEndpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => env("ACCESS_TOKEN"),
            ],
            'json' => [
                'query' => $query,
            ],
        ]);


        $data = json_decode($response->getBody(), true);

        Log::info("Varint IDS",$data);
        $inventoryLevelId = $data['data']['productVariants']['edges'][0]['node']['inventoryItem']['inventoryLevels']['edges'][0]['node']['id'] ?? null;
        $inventoryItemId = $data['data']['productVariants']['edges'][0]['node']['inventoryItem']['id'];
        $variantId =  $data['data']['productVariants']['edges'][0]['node']['id'];
        return [
            'inventoryLevelId' => $inventoryLevelId,
            'inventoryItemId' => $inventoryItemId,
            'variantId' => $variantId
        ];
    }
}
