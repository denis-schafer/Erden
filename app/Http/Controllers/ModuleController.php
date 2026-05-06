<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = Module::orderBy('order')->get();
        return response()->json($modules);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'route' => 'required',
            'icon' => 'nullable',
            'description' => 'nullable',
            'is_special' => 'boolean',
            'parent_id' => 'nullable',
            'order' => 'integer',
            'package' => 'nullable'
        ]);

        $module = Module::create($validated);
        return response()->json(['id' => $module->id, 'message' => 'Módulo creado']);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required',
            'route' => 'required',
            'icon' => 'nullable',
            'description' => 'nullable',
            'is_special' => 'boolean',
            'parent_id' => 'nullable',
            'order' => 'integer',
            'package' => 'nullable'
        ]);

        Module::where('id', $id)->update($validated);
        return response()->json(['message' => 'Módulo actualizado']);
    }

    public function destroy($id)
    {
        Module::where('id', $id)->delete();
        return response()->json(['message' => 'Módulo eliminado']);
    }
}