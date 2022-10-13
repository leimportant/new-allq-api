<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kasbon extends Model
{

    public $incrementing = false;

    protected $table = 'kasbon';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
