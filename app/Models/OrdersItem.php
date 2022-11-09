<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdersItem extends Model
{
    use HasFactory;

    protected $table = 'create_orders_items';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
