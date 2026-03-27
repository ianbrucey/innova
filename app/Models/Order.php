<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $table;
    protected $primaryKey = 'OrderID';
    public $timestamps = false;

    const STATUS_READY_TO_SHIP = 5;
    const STATUS_COMPLETE      = 4;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('services.innova_schema.tables.order');
    }

    protected $fillable = [
        'AdminOrderStatus',
        'UpdateDate',
    ];

    protected $casts = [
        'UpdateDate' => 'datetime',
    ];

    public function lineItems(): HasMany
    {
        return $this->hasMany(OrderLineItem::class, 'OrderID', 'OrderID');
    }

    public function shipping(): HasOne
    {
        return $this->hasOne(OrderShipping::class, 'OrderID', 'OrderID');
    }

    public function scopeReadyToShip($query)
    {
        return $query->where(
            config('services.innova_schema.columns.admin_order_status'),
            self::STATUS_READY_TO_SHIP
        );
    }

    public function isRejectedByAmazon(): bool
    {
        // Tags are stored as a comma-separated string on the Shopify order payload
        // and synced into the local DB. Column name TBC from schema.
        // TODO: confirm column name once schema dump received
        $tags = $this->tags ?? '';
        return str_contains(strtolower($tags), 'rejected by amazon');
    }
}
