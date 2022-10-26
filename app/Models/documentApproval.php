<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class documentApproval extends Model
{   
    use HasFactory, Notifiable;
    public $incrementing = false;
    const CREATED_AT  = null;
    const UPDATED_AT = null;

    protected $table = 'document_approvals';
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}