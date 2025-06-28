<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyWebhookController;

Route::get('/', function () {
    return view('welcome');
});


Route::post('/shopify/order-created', [ShopifyWebhookController::class, 'handle']);
