<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $connection = 'mysql_parent';
    protected $table = 'statuses';
    public $timestamps = false;
    protected $fillable = ['name'];
}