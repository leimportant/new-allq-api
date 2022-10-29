<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchaseorderitem extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_item';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
