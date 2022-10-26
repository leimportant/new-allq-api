<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class configApproval extends Model
{
    use HasFactory;

    protected $table = 'config_approvals';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
