<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdersAssignJob extends Model
{
    use HasFactory;

    protected $table = 'create_orders_assignjob';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
