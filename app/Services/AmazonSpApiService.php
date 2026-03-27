<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SellingPartnerApi\SellingPartnerApi;
use SellingPartnerApi\Enums\Endpoint;

class AmazonSpApiService
{
    private const CACHE_TTL_MINUTES = 30;

    private function connector()
    {
        return SellingPartnerApi::make(
            clientId:     config('services.amazon.client_id'),
            clientSecret: config('services.amazon.client_secret'),
            refreshToken: config('services.amazon.refresh_token'),
            endpoint:     Endpoint::NA,
        );
    }

    /**
     * Check whether Amazon has an order matching this Shopify order number.
     *
     * Cached per order number for 30 minutes to avoid burning SP-API rate limits
     * across multiple engine cycles in the same polling window.
     *
     * Returns true  → Amazon is fulfilling this order → skip Irvine
     * Returns false → Amazon has no record → Irvine must fulfill
     */
    public function isAmazonFulfilling(string $shopifyOrderNumber): bool
    {
        $cacheKey = "amazon_order_{$shopifyOrderNumber}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($shopifyOrderNumber) {
            try {
                $ordersApi = $this->connector()->ordersV0();

                $response = $ordersApi->getOrders(
                    marketplaceIds: [config('services.amazon.marketplace_id')],
                    // TODO: confirm whether Innova cross-references by buyer order ID or a custom field
                    buyerOrderId: $shopifyOrderNumber,
                );

                $orders = $response->json('payload.Orders', []);
                return count($orders) > 0;

            } catch (\Exception $e) {
                Log::error('AmazonSpApiService: order lookup failed', [
                    'order'   => $shopifyOrderNumber,
                    'message' => $e->getMessage(),
                ]);

                // Re-throw — do not default to "Irvine" on API failure,
                // that could cause double-shipments.
                throw $e;
            }
        });
    }

    /**
     * Get the SKUs Amazon is fulfilling for an order.
     * Used for split orders to identify which specific lines Amazon has.
     */
    public function getAmazonFulfilledSkus(string $shopifyOrderNumber): array
    {
        $cacheKey = "amazon_skus_{$shopifyOrderNumber}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($shopifyOrderNumber) {
            try {
                $ordersApi = $this->connector()->ordersV0();

                $response = $ordersApi->getOrders(
                    marketplaceIds: [config('services.amazon.marketplace_id')],
                    buyerOrderId: $shopifyOrderNumber,
                );

                $orders = $response->json('payload.Orders', []);

                if (empty($orders)) {
                    return [];
                }

                $amazonOrderId = $orders[0]['AmazonOrderId'];

                // Fetch the line items for this Amazon order
                $itemsResponse = $ordersApi->getOrderItems($amazonOrderId);
                $items = $itemsResponse->json('payload.OrderItems', []);

                return array_column($items, 'SellerSKU');

            } catch (\Exception $e) {
                Log::error('AmazonSpApiService: getAmazonFulfilledSkus failed', [
                    'order'   => $shopifyOrderNumber,
                    'message' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }
}
