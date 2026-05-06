<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyModule extends Model
{
    protected $connection = 'mysql_parent';
    protected $table = 'company_modules';
    public $timestamps = false;
    protected $fillable = ['company_id', 'module_id', 'order'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
}