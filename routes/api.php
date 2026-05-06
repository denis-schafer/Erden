<?php

use Illuminate\Support\Facades\Route;

// MercadoPago Webhook - Rutas API no usan CSRF
Route::post('/mp/webhook', [\App\Http\Controllers\Pos\MercadoPagoController::class, 'webhook']);
