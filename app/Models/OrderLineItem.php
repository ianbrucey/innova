<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLineItem extends Model
{
    protected $table;
    public $timestamps = false;

    const SOURCE_AMAZON = 'AMAZON';
    const SOURCE_IRVINE = 'IRVINE';

    const LINE_STATUS_PENDING       = 0;
    const LINE_STATUS_READY_TO_SHIP = 5;
    const LINE_STATUS_COMPLETE      = 4;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('services.innova_schema.tables.order_line_item');
    }

    protected $fillable = [
        'OrderID',
        'SKU',
        'Quantity',
        'RequiresShipping',
        'TrackingNumber',
        'ShopifyLineItemId',
        // New columns added by our migration:
        'FulfillmentSource',
        'LineItemShipStatus',
        'FulfillmentResolvedAt',
    ];

    protected $casts = [
        'RequiresShipping'      => 'boolean',
        'FulfillmentResolvedAt' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'OrderID', 'OrderID');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeRequiresShipping($query)
    {
        return $query->where(
            config('services.innova_schema.columns.requires_shipping'),
            1
        );
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('FulfillmentSource');
    }

    public function scopeIrvineRouted($query)
    {
        return $query->where('FulfillmentSource', self::SOURCE_IRVINE);
    }

    public function scopeReadyToShip($query)
    {
        return $query->where('LineItemShipStatus', self::LINE_STATUS_READY_TO_SHIP);
    }

    public function scopeHasTracking($query)
    {
        $col = config('services.innova_schema.columns.tracking_number');
        return $query->whereNotNull($col)->where($col, '!=', '');
    }

    // -------------------------------------------------------------------------
    // Routing actions
    // -------------------------------------------------------------------------

    public function markAsIrvine(): void
    {
        $this->update([
            'FulfillmentSource'     => self::SOURCE_IRVINE,
            'LineItemShipStatus'    => self::LINE_STATUS_READY_TO_SHIP,
            'FulfillmentResolvedAt' => now(),
        ]);
    }

    public function markAsAmazon(): void
    {
        $this->update([
            'FulfillmentSource'     => self::SOURCE_AMAZON,
            'FulfillmentResolvedAt' => now(),
        ]);
    }

    public function markComplete(): void
    {
        $this->update(['LineItemShipStatus' => self::LINE_STATUS_COMPLETE]);
    }

    public function getTrackingNumber(): ?string
    {
        $col = config('services.innova_schema.columns.tracking_number');
        return $this->$col ?? null;
    }
}
