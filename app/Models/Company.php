<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $connection = 'mysql_parent';
    protected $table = 'companies';
    public $timestamps = false;
    protected $fillable = ['db', 'name', 'status_id'];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function modules()
    {
        return $this->hasMany(CompanyModule::class, 'company_id');
    }
}