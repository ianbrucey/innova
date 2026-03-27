<?php

namespace App\Console\Commands;

use App\Models\OrderLineItem;
use App\Services\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunNightlySync extends Command
{
    protected $signature   = 'innova:sync-fulfillments
                                {--dry-run : Log what would be pushed to Shopify without actually calling the API}';
    protected $description = 'Push FedEx tracking numbers to Shopify and mark Irvine-fulfilled line items complete';

    public function handle(ShopifyService $shopify): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no Shopify API calls or DB writes will occur');
        }

        $this->info('Starting nightly fulfillment sync...');

        // Find every Irvine-routed line item that:
        //   - is ready to ship (LineItemShipStatus = 5)
        //   - has a FedEx tracking number (written by the warehouse WMS)
        $lineItems = OrderLineItem::with('order')
            ->irvineRouted()
            ->readyToShip()
            ->hasTracking()
            ->get();

        if ($lineItems->isEmpty()) {
            $this->info('No line items ready for sync. Exiting.');
            return Command::SUCCESS;
        }

        $this->info("{$lineItems->count()} line item(s) ready to sync.");

        // Group by Shopify order ID so we make one API call per order
        // TODO: confirm which column stores the Shopify order ID on the local Order record
        $grouped = $lineItems->groupBy(fn($item) => $item->order->shopify_order_id);

        $success = 0;
        $failed  = 0;

        foreach ($grouped as $shopifyOrderId => $items) {
            $trackingNumber = $items->first()->getTrackingNumber();

            $this->line("  Order {$shopifyOrderId} — {$items->count()} line item(s) — tracking: {$trackingNumber}");

            if ($dryRun) {
                $success++;
                continue;
            }

            // Map our local line item IDs to Shopify's FulfillmentOrder line item IDs.
            // These are two different ID spaces — mapping is required by the current API.
            $shopifyLineItemIds = $items->pluck('ShopifyLineItemId')->filter()->toArray();

            $fulfillmentItems = $shopify->mapToFulfillmentLineItems($shopifyOrderId, $shopifyLineItemIds);

            if (empty($fulfillmentItems)) {
                $this->warn("  Could not map line items for order {$shopifyOrderId} — skipping");
                Log::warning('NightlySync: line item mapping returned empty', ['order_id' => $shopifyOrderId]);
                $failed++;
                continue;
            }

            $ok = $shopify->createFulfillment(
                shopifyOrderId:              $shopifyOrderId,
                lineItemsByFulfillmentOrder: $fulfillmentItems,
                trackingNumber:              $trackingNumber,
            );

            if ($ok) {
                // Mark these specific lines complete — NOT the entire order
                $ids = $items->pluck($items->first()->getKeyName());
                OrderLineItem::whereIn($items->first()->getKeyName(), $ids)->update([
                    'LineItemShipStatus' => OrderLineItem::LINE_STATUS_COMPLETE,
                ]);
                $this->info("  ✓ Order {$shopifyOrderId} fulfilled");
                Log::info('NightlySync: order fulfilled', ['order_id' => $shopifyOrderId, 'tracking' => $trackingNumber]);
                $success++;
            } else {
                $this->error("  ✗ Order {$shopifyOrderId} failed");
                $failed++;
            }
        }

        $this->info("Sync complete — {$success} succeeded, {$failed} failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
