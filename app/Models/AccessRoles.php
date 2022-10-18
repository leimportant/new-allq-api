<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessRoles extends Model
{
    public $incrementing = false;

    protected $table = 'access_roles';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function menu()
    {
        return $this->hasMany(AccessMenu::class, 'id', 'menu_id');
    }
}
