<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderShipping extends Model
{
    protected $table;
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('services.innova_schema.tables.order_shipping');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'OrderID', 'OrderID');
    }
}
