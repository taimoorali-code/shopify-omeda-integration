<?php

namespace App\Services;

use App\Models\ShopifyWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OmedaIntegrationService
{
    public function handleSubscription(string $type, array $order, array $item): array
    {
        // Log::info("OmedaIntegrationService: Handling subscription of type: $type");

        switch ($type) {
            case 'new':
                return $this->handleNewSubscription($order, $item);
            case 'renew':
            case 'gift':
                return ['status' => 501, 'body' => ['message' => 'Not implemented']];
            default:
                return ['status' => 400, 'body' => ['message' => 'Invalid subscription type']];
        }
    }

    private function handleNewSubscription(array $order, array $item): array
    {
        try {
            $customer = $order['customer'] ?? [];
            $shipping = $order['shipping_address'] ?? [];
            $billing = $order['billing'] ?? [];
            $meta = $order['metadata'] ?? [];


            $body = [
                'FirstName' => $customer['first_name'],
                'LastName' => $customer['last_name'],

                'Addresses' => [
                    [
                        'Street' => $shipping['address1'],
                        'City' => $shipping['city'],
                        'PostalCode' => $shipping['zip'],
                        'Country' => $shipping['country_code'],
                    ]
                ],

                'Emails' => [
                    [
                        'EmailAddress' => $customer['email'],
                    ]
                ],

                'BillingInformation' => [
                    'BillingCompany' => $billing['company'],
                    'BillingStreet' => $billing['street'],
                    'BillingApartmentMailStop' => $billing['apartment'],
                    'BillingCity' => $billing['city'],
                    'BillingRegion' => $billing['region'],
                    'BillingPostalCode' => $billing['postal_code'],
                    'BillingCountryCode' => $billing['country_code'],
                    'DoCharge' => $meta['do_charge'] ? 'True' : 'False',
                    'Comment1' => $meta['comment1'],
                    'Comment2' => 'subscription_id: ' . $meta['subscription_id'],
                ],
                'RunProcessor' => 1,
                'Products' => [
                    [
                        'OmedaProductId' => (int) env('OMEDA_PRODUCT_ID'),
                        'RequestedVersion' => 'D',
                        'Term' => '12',
                        'Amount' => $item['price'],
                        'AmountPaid' => '0.00',
                        'SalesTax' => '0.00',
                        'Receive' => 1
                    ]
                ]
            ];

            // If charging is true, add deposit info
            if ($meta['do_charge'] === true) {
                $body['BillingInformation']['DepositDate'] = now()->format('Y-m-d');
                $body['BillingInformation']['AuthCode'] = 'RECHARGE'; // Or any valid mock/test value
            }

            Log::info('Posting formatted subscription to Omeda:', $body);

            $response = Http::withHeaders([
                'x-omeda-appid' => env('OMEDA_APPID'),
                'x-omeda-inputid' => env('OMEDA_INPUT_ID'),
                'x-api-key' => env('OMEDA_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://ows.omeda.com/webservices/rest/brand/EWPM/storecustomerandorder/*', $body);

            Log::info('Omeda response:', [
                'status' => $response->status(),
                'body' => json_decode($response->body(), true),
            ]);
            if ($response->status() === 200) {
                $responseBody = json_decode($response->body(), true);
                $responseStatus = $response->status();

                ShopifyWebhook::create([
                    'payload' => $body,
                    'response' => [
                        'status' => $responseStatus,
                        'body' => $responseBody,
                    ],
                ]);

              

                Log::info("Saved subscription order to DB.");

            } else {
                Log::warning("Omeda API failed. Status: {$response['status']}", $response['body']);
            }

            return [
                'status' => $response->status(),
                'body' => json_decode($response->body(), true),
            ];
        } catch (\Throwable $e) {
            Log::error("Omeda submission failed: " . $e->getMessage());
            return [
                'status' => 500,
                'body' => ['error' => $e->getMessage()]
            ];
        }
    }




}
