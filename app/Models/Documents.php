<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Documents extends Model
{
    use HasFactory;

    protected $table = 'documents';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
}
