<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class PosRoleController extends Controller
{
    public function index()
    {
        $roles = DB::table('roles')->orderBy('name')->get();
        return response()->json($roles);
    }
}
