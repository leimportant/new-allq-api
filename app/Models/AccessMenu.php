<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessMenu extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $table = 'access_menu';

    public function role()
    {
        return $this->hasMany(AccessRoles::class, 'menu_id', 'id');
    }
}
