<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodIssueMaterial extends Model
{
    use HasFactory;

    protected $table = 'good_issue_material';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
}
