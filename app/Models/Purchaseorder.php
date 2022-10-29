<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchaseorder extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $table = 'purchase_order';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function items()
    {
        return $this->hasMany(Purchaseorderitem::class, 'purchase_id', 'id');
    }

    public function activities()
    {
        return $this->hasMany(Activities::class, 'transaction_id', 'id');
    }
}
