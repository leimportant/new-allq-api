<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodIssue extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $table = 'good_issue';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
