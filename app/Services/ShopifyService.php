<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class ShopifyService
{
    private string $baseUrl;
    private array  $headers;

    private const RATE_LIMIT_CUSHION = 0.80;

    public function __construct()
    {
        $domain  = config('services.shopify.domain');
        $version = config('services.shopify.api_version', '2025-01');

        $this->baseUrl = "https://{$domain}/admin/api/{$version}";
        $this->headers = [
            'X-Shopify-Access-Token' => config('services.shopify.access_token'),
            'Content-Type'           => 'application/json',
        ];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function get(string $url, array $params = []): Response
    {
        $fullUrl = $params ? $url . '?' . http_build_query($params) : $url;
        return $this->requestWithRetry('get', $fullUrl);
    }

    private function post(string $url, array $payload): Response
    {
        return $this->requestWithRetry('post', $url, $payload);
    }

    private function requestWithRetry(string $method, string $url, array $payload = []): Response
    {
        $attempts = 0;

        do {
            $response = Http::withHeaders($this->headers)->{$method}($url, $payload ?: null);

            if ($response->status() === 429) {
                $wait = (float) ($response->header('Retry-After') ?? 2);
                Log::warning("Shopify 429 — waiting {$wait}s", ['url' => $url]);
                sleep((int) ceil($wait));
                $attempts++;
                continue;
            }

            $this->throttleIfNeeded($response);
            return $response;

        } while ($attempts < 3);

        return $response;
    }

    private function throttleIfNeeded(Response $response): void
    {
        $header = $response->header('X-Shopify-Shop-Api-Call-Limit');
        if (!$header) return;

        [$used, $max] = array_map('intval', explode('/', $header));
        if ($max > 0 && ($used / $max) >= self::RATE_LIMIT_CUSHION) {
            sleep(1);
        }
    }

    private function parseNextLink(?string $linkHeader): ?string
    {
        if (!$linkHeader) return null;
        foreach (explode(',', $linkHeader) as $part) {
            if (str_contains($part, 'rel="next"')) {
                preg_match('/<([^>]+)>/', trim($part), $m);
                return $m[1] ?? null;
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Order fetching
    // -------------------------------------------------------------------------

    public function getOpenOrders(): Collection
    {
        return collect()
            ->merge($this->paginateOrders(['status' => 'open', 'fulfillment_status' => 'unfulfilled', 'limit' => 250]))
            ->merge($this->paginateOrders(['status' => 'open', 'fulfillment_status' => 'partial',      'limit' => 250]));
    }

    private function paginateOrders(array $params): Collection
    {
        $all     = collect();
        $nextUrl = "{$this->baseUrl}/orders.json?" . http_build_query($params);

        while ($nextUrl) {
            $response = $this->requestWithRetry('get', $nextUrl);

            if ($response->failed()) {
                Log::error('Shopify: failed to fetch orders page', ['status' => $response->status()]);
                break;
            }

            $all     = $all->merge($response->json('orders', []));
            $nextUrl = $this->parseNextLink($response->header('Link'));
        }

        return $all;
    }

    // -------------------------------------------------------------------------
    // Fulfillment
    // -------------------------------------------------------------------------

    public function getFulfillmentOrders(string|int $shopifyOrderId): Collection
    {
        $response = $this->get("{$this->baseUrl}/orders/{$shopifyOrderId}/fulfillment_orders.json");

        if ($response->failed()) {
            Log::error('Shopify: getFulfillmentOrders failed', [
                'order_id' => $shopifyOrderId,
                'status'   => $response->status(),
            ]);
            return collect();
        }

        return collect($response->json('fulfillment_orders', []))
            ->filter(fn($fo) =>
                $fo['status'] === 'open' &&
                in_array('create_fulfillment', $fo['supported_actions'] ?? [], strict: true)
            );
    }

    /**
     * Map order-level line item IDs (what we store locally) to the
     * FulfillmentOrder line item IDs Shopify's API actually requires.
     */
    public function mapToFulfillmentLineItems(string|int $shopifyOrderId, array $shopifyLineItemIds): array
    {
        $mapped = [];

        foreach ($this->getFulfillmentOrders($shopifyOrderId) as $fo) {
            foreach ($fo['line_items'] as $foItem) {
                if (in_array($foItem['line_item_id'], $shopifyLineItemIds, strict: false)) {
                    $foId = $fo['id'];
                    $mapped[$foId][] = [
                        'id'       => $foItem['id'],
                        'quantity' => $foItem['fulfillable_quantity'],
                    ];
                }
            }
        }

        // Build the consolidated payload structure Shopify requires.
        // Duplicate fulfillment_order_ids in one request are rejected (breaking change 2024-04).
        return array_map(
            fn($foId, $items) => [
                'fulfillment_order_id'         => $foId,
                'fulfillment_order_line_items' => $items,
            ],
            array_keys($mapped),
            array_values($mapped)
        );
    }

    public function createFulfillment(
        string|int $shopifyOrderId,
        array      $lineItemsByFulfillmentOrder,
        string     $trackingNumber,
        string     $trackingCompany = 'FedEx'
    ): bool {
        if (empty($lineItemsByFulfillmentOrder)) {
            Log::warning('Shopify: createFulfillment called with empty line items', [
                'order_id' => $shopifyOrderId,
            ]);
            return false;
        }

        $response = $this->post("{$this->baseUrl}/fulfillments.json", [
            'fulfillment' => [
                'line_items_by_fulfillment_order' => array_values($lineItemsByFulfillmentOrder),
                'tracking_info' => [
                    'company' => $trackingCompany,
                    'number'  => $trackingNumber,
                ],
                'notify_customer' => true,
            ],
        ]);

        if ($response->failed() || $response->json('fulfillment.status') === 'failure') {
            Log::error('Shopify: createFulfillment failed', [
                'order_id' => $shopifyOrderId,
                'tracking' => $trackingNumber,
                'status'   => $response->status(),
                'body'     => $response->json(),
            ]);
            return false;
        }

        return true;
    }
}
