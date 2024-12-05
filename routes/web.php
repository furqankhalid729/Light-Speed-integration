<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyWebhooksController;
use App\Http\Controllers\LightSpeedWebhooksController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/order-created', [ShopifyWebhooksController::class, 'orderCreated']);
Route::post('/product-created', [ShopifyWebhooksController::class, 'productCreated']);

Route::post('/lightspeed-product-updated', [LightSpeedWebhooksController::class, 'productUpdated']);
Route::post('/lightspeed-inventory-updated', [LightSpeedWebhooksController::class, 'inventoryUpdated']);


Route::get('get-id/{sku}', [ShopifyWebhooksController::class, 'getCustomSKU']);
