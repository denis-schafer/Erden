<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $connection = 'mysql_parent';
    protected $table = 'modules';
    public $timestamps = false;
    protected $fillable = ['name', 'route', 'icon', 'description', 'is_special', 'parent_id', 'order', 'package'];

    protected $casts = [
        'is_special' => 'boolean',
    ];

    public function children()
    {
        return $this->hasMany(Module::class, 'parent_id')->orderBy('order');
    }

    public function parent()
    {
        return $this->belongsTo(Module::class, 'parent_id');
    }
}