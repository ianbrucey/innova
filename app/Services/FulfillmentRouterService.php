<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderLineItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FulfillmentRouterService
{
    public function __construct(
        private readonly ShopifyService     $shopify,
        private readonly AmazonSpApiService $amazon,
    ) {}

    public function run(): void
    {
        Log::info('FulfillmentRouter: starting run');

        $orders = $this->shopify->getOpenOrders();

        Log::info("FulfillmentRouter: {$orders->count()} open orders fetched from Shopify");

        foreach ($orders as $shopifyOrder) {
            try {
                $this->routeOrder($shopifyOrder);
            } catch (\Exception $e) {
                Log::error('FulfillmentRouter: error routing order', [
                    'order_number' => $shopifyOrder['order_number'] ?? '?',
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        Log::info('FulfillmentRouter: run complete');
    }

    private function routeOrder(array $shopifyOrder): void
    {
        $orderNumber = (string) $shopifyOrder['order_number'];

        // Find this order in the local DB
        // TODO: confirm the column that stores the Shopify order number locally
        $localOrder = Order::where('shopify_order_number', $orderNumber)->first();

        if (!$localOrder) {
            Log::debug("FulfillmentRouter: order #{$orderNumber} not in local DB — skipping");
            return;
        }

        $unresolved = $localOrder->lineItems()
            ->requiresShipping()
            ->unresolved()
            ->get();

        if ($unresolved->isEmpty()) {
            return;
        }

        // -------------------------------------------------------------------
        // Fast path: WebBee already tagged this order "rejected by Amazon"
        // Every line item goes to Irvine. No SP-API call needed.
        // -------------------------------------------------------------------
        if ($localOrder->isRejectedByAmazon()) {
            Log::info("FulfillmentRouter: order #{$orderNumber} rejected by Amazon — routing all to Irvine");
            $this->markAllIrvine($unresolved);
            return;
        }

        // -------------------------------------------------------------------
        // Split path: ask Amazon which SKUs they are handling
        // Amazon's decision is binary per line — they take the whole line or reject it.
        // -------------------------------------------------------------------
        $amazonSkus = $this->amazon->getAmazonFulfilledSkus($orderNumber);

        foreach ($unresolved as $item) {
            if (in_array($item->SKU, $amazonSkus, strict: true)) {
                $item->markAsAmazon();
                Log::debug("FulfillmentRouter: order #{$orderNumber} line {$item->getKey()} → AMAZON");
            } else {
                $item->markAsIrvine();
                Log::debug("FulfillmentRouter: order #{$orderNumber} line {$item->getKey()} → IRVINE");
            }
        }
    }

    private function markAllIrvine(Collection $items): void
    {
        $ids = $items->pluck($items->first()->getKeyName());

        OrderLineItem::whereIn($items->first()->getKeyName(), $ids)->update([
            'FulfillmentSource'     => OrderLineItem::SOURCE_IRVINE,
            'LineItemShipStatus'    => OrderLineItem::LINE_STATUS_READY_TO_SHIP,
            'FulfillmentResolvedAt' => now(),
        ]);
    }
}
