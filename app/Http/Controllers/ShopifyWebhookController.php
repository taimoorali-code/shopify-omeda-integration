<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Optional: Verify HMAC here
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $calculated = base64_encode(hash_hmac('sha256', $request->getContent(), env('SHOPIFY_WEBHOOK_SECRET'), true));

        if (!hash_equals($hmac, $calculated)) {
            Log::warning('Shopify webhook HMAC mismatch');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Log or save order data
        $order = $request->all();
        Log::info('Received Shopify Order', $order);

        // TODO: Save to DB or queue for processing later

        return response()->json(['status' => 'ok']);
    }
}
