<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel;
use App\Console\Commands\MigrationAll;
use Illuminate\Support\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{
    protected $commands = [
        MigrationAll::class,
    ];

    public function boot()
    {
        //
    }
}