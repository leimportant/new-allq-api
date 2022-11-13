<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use HasFactory;
    protected $table = 'sizing';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
