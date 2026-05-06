<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalUser extends Model
{
    protected $connection = 'mysql_parent';
    protected $table = 'global_users';
    public $timestamps = false;
    protected $fillable = ['username', 'password', 'role_id', 'company_id', 'is_global'];

    protected $hidden = ['password'];

    public function role()
    {
        return $this->belongsTo(GlobalRole::class, 'role_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}