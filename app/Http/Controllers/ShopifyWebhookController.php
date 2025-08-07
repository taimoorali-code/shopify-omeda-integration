<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\ShopifyWebhook;
use App\Services\OmedaIntegrationService;

class ShopifyWebhookController extends Controller
{
    protected $omedaService;

    public function __construct(OmedaIntegrationService $omedaService)
    {
        $this->omedaService = $omedaService;
    }
     public function index()
    {
        $logs = ShopifyWebhook::latest()->paginate(10);
        return view('omedalogs', compact('logs'));
    }

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

        $data = $request->all();
        $hasSubscription = false;

        foreach ($data['line_items'] ?? [] as $item) {
            foreach ($item['properties'] ?? [] as $property) {
                if ($property['name'] === 'subscription_type' && !empty($property['value'])) {
                    $hasSubscription = true;

                    // Log::info("Handling subscription type [{$property['value']}] for product: {$item['title']}");

                    $response = $this->omedaService->handleSubscription($property['value'], $data, $item);

                    if ($response['status'] === 200) {
                        ShopifyWebhook::create([
                            'payload' => $data,
                            'response' => $response,
                        ]);

                        Log::info("Saved subscription order to DB.");

                        // ✅ Save Omeda customer ID to Shopify
                        $omedaCustomerId = $response['body']['ResponseInfo'][0]['CustomerId'] ?? null;
                        $shopifyCustomerId = $data['customer']['id'] ?? null;

                        // if ($omedaCustomerId && $shopifyCustomerId) {
                        //     $this->updateShopifyCustomerMetafield($shopifyCustomerId, $omedaCustomerId);
                        // }
                    } else {
                        Log::warning("Omeda API failed. Status: {$response['status']}", $response['body']);
                    }

                    break 2;
                }
            }
        }

        if (!$hasSubscription) {
            Log::info("Order skipped — not a subscription product.");
            return response()->json(['status' => 'skipped — not a subscription']);
        }

        return response()->json(['status' => 'ok']);
    }   
   
public function rechargeSubscriptionCreated(Request $request)
{
    $subscription = $request->input('subscription');

    if (!$subscription) {
        return response()->json(['error' => 'Invalid payload'], 400);
    }

    $details = $this->fetchRechargeCustomerAndAddress(
        $subscription['customer_id'],
        $subscription['address_id']
    );

    $customer = $details['customer'];
    $address = $details['address'];

    // Build order
    $order = [
        'customer' => [
            'first_name' => $customer['first_name'] ?? '',
            'last_name' => $customer['last_name'] ?? '',
            'email' => $customer['email'] ?? '',
        ],
        'shipping_address' => [
            'address1' => $address['address1'] ?? '',
            'city' => $address['city'] ?? '',
            'zip' => $address['zip'] ?? '',
            'country_code' => $address['country_code'] ?? 'US',
        ],
        'billing' => [
            'company' => $address['company'] ?? 'None',
            'street' => $address['address1'] ?? '',
            'apartment' => $address['address2'] ?? '',
            'city' => $address['city'] ?? '',
            'region' => $address['province'] ?? '',
            'postal_code' => $address['zip'] ?? '',
            'country_code' => $address['country_code'] ?? 'US',
        ],
        'metadata' => [
            'do_charge' => false,
            'subscription_id' => $subscription['id'] ?? null,
            'comment1' => 'Shopify Recharge Checkout',
        ]
    ];

    $item = [
        'price' => $subscription['price'] ?? '0.00',
        'title' => $subscription['product_title'] ?? '',
        'shopify_product_id' => $subscription['shopify_product_id'] ?? '',
    ];
    Log::info("Sending Order to Omeda for Recharge Subscription", [
        'order' => $order,
        'item' => $item,
    ]);
    $omedaService = new OmedaIntegrationService();
    $response = $omedaService->handleSubscription('new', $order, $item);

    return response()->json($response, $response['status']);
}

private function fetchRechargeCustomerAndAddress($customerId, $addressId)
{
    Log::info('Receiving customer id: ' . $customerId . ' and address id: ' . $addressId);
    $headers = [
        'X-Recharge-Access-Token' => env('RECHARGE_API_TOKEN'),
        'Accept' => 'application/json',
    ];

    // Get Customer
    $customerResponse = Http::withHeaders($headers)
        ->get("https://api.rechargeapps.com/customers/{$customerId}");

    if (!$customerResponse->successful()) {
        Log::error("Failed to fetch Recharge customer. Response: " . $customerResponse->body());
    }

    // Get Address
    $addressResponse = Http::withHeaders($headers)
        ->get("https://api.rechargeapps.com/addresses/{$addressId}");

    if (!$addressResponse->successful()) {
        Log::error("Failed to fetch Recharge address. Response: " . $addressResponse->body());
    }

    return [
        'customer' => $customerResponse->json()['customer'] ?? [],
        'address' => $addressResponse->json()['address'] ?? [],
    ];
}



}
