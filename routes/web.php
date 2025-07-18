<?php

use App\Http\Controllers\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

// routes/web.php
Route::get('/', [ShopifyWebhookController::class, 'index'])->name('omeda.logs');
