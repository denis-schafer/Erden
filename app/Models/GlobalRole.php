<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalRole extends Model
{
    protected $connection = 'mysql_parent';
    protected $table = 'roles';
    public $timestamps = false;
    protected $fillable = ['name', 'slug', 'is_global'];

    protected $casts = [
        'is_global' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(GlobalUser::class, 'role_id');
    }
}