<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    use HasFactory;

    protected $table = 'create_orders';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function details()
    {
        return $this->hasMany(OrdersDetail::class, 'orders_id', 'id');
    }

    public function activities()
    {
        return $this->hasMany(Activities::class, 'transaction_id', 'id');
    }
}
