<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ShopifyWebhook;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $calculated = base64_encode(
            hash_hmac('sha256', $request->getContent(), env('SHOPIFY_WEBHOOK_SECRET'), true)
        );

        if (!hash_equals($hmac, $calculated)) {
            Log::warning('Shopify webhook HMAC mismatch');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Optional: log raw for debugging
        Log::info('Received Shopify Order', $request->all());

        // ✅ Save to DB
        ShopifyWebhook::create([
            'payload' => $request->all(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}

