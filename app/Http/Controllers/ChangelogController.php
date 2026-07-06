<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChangelogController extends Controller
{
    public function index(Request $request)
    {
        $module = $request->get('module');

        $entries = DB::table('changelog_entries')
            ->when($module, function ($q) use ($module) {
                $modules = is_array($module) ? $module : explode(',', $module);
                $q->whereIn('module', $modules);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($entries);
    }

    public function dismiss(Request $request)
    {
        $module = $request->get('module');

        DB::table('changelog_entries')
            ->when($module, function ($q) use ($module) {
                $modules = is_array($module) ? $module : explode(',', $module);
                $q->whereIn('module', $modules);
            })
            ->where('is_published', true)
            ->update(['is_published' => false, 'updated_at' => now()]);

        return response()->json(['success' => true]);
    }
}
