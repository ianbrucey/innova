<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds line-item level fulfillment routing fields to the OrderLineItem table.
 *
 * This is the core schema change that enables split-order routing.
 * The existing system tracks status at the order header level only.
 * These columns let us track routing decisions per line item independently.
 *
 * BEFORE running on production:
 *   1. Confirm the table name matches DB_TABLE_ORDER_LINE_ITEM in .env
 *   2. Test against a copy of the production database first
 *   3. Coordinate with whoever owns the MS Access views — they may need updating
 *
 * The existing AdminOrderStatus on the Order table is left untouched.
 * The warehouse's Access view continues to work as before during transition.
 */
return new class extends Migration
{
    private function table(): string
    {
        return config('services.innova_schema.tables.order_line_item');
    }

    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            // Which system is fulfilling this line?
            $table->string('FulfillmentSource', 10)
                  ->nullable()
                  ->default(null)
                  ->comment('AMAZON | IRVINE | null (unresolved)');

            // Line-item level ship status, mirrors order-level AdminOrderStatus
            $table->smallInteger('LineItemShipStatus')
                  ->default(0)
                  ->comment('0=pending, 5=ready to ship, 4=complete');

            // When was the routing decision written?
            $table->dateTime('FulfillmentResolvedAt')
                  ->nullable();

            // Shopify's internal line item ID — required to call the Fulfillment API
            // for specific lines rather than closing the entire order
            $table->string('ShopifyLineItemId', 50)
                  ->nullable();
        });

        // Composite index for the nightly sync query:
        // WHERE FulfillmentSource = 'IRVINE' AND LineItemShipStatus = 5 AND TrackingNumber IS NOT NULL
        Schema::table($this->table(), function (Blueprint $table) {
            $table->index(
                ['FulfillmentSource', 'LineItemShipStatus'],
                'idx_fulfillment_routing'
            );
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropIndex('idx_fulfillment_routing');
            $table->dropColumn([
                'FulfillmentSource',
                'LineItemShipStatus',
                'FulfillmentResolvedAt',
                'ShopifyLineItemId',
            ]);
        });
    }
};
