<?php

namespace App\Http\Controllers\Academy\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyPortalController extends Controller
{
    public function lookupCompany(Request $request)
    {
        $name = $request->get('name');
        $courseSlug = $request->get('course_slug');

        if (empty($name) && empty($courseSlug)) {
            return response()->json(['error' => 'Ingrese un nombre de empresa o slug de curso'], 400);
        }

        if ($courseSlug) {
            return $this->lookupByCourseSlug($courseSlug);
        }

        $company = DB::connection('mysql_parent')
            ->table('companies')
            ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($name) . '%'])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('company_modules')
                    ->join('modules', 'company_modules.module_id', '=', 'modules.id')
                    ->whereColumn('company_modules.company_id', 'companies.id')
                    ->where('modules.package', 'academy');
            })
            ->first();

        if (!$company) {
            return response()->json(['error' => 'Empresa no encontrada o no tiene el módulo Academy'], 404);
        }

        return response()->json([
            'id' => $company->id,
            'name' => $company->name,
            'db' => $company->db,
        ]);
    }

    protected function lookupByCourseSlug($slug)
    {
        $companies = DB::connection('mysql_parent')
            ->table('companies')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('company_modules')
                    ->join('modules', 'company_modules.module_id', '=', 'modules.id')
                    ->whereColumn('company_modules.company_id', 'companies.id')
                    ->where('modules.package', 'academy');
            })
            ->get();

        foreach ($companies as $company) {
            config(['database.connections.mysql.database' => $company->db]);
            DB::purge('mysql');
            DB::reconnect('mysql');

            try {
                $course = DB::table('academy_courses')
                    ->where('slug', $slug)
                    ->where('is_published', true)
                    ->first();

                if ($course) {
                    config(['database.connections.mysql.database' => 'erden']);
                    DB::purge('mysql');
                    DB::reconnect('mysql');

                    return response()->json([
                        'id' => $company->id,
                        'name' => $company->name,
                        'db' => $company->db,
                        'course_slug' => $course->slug,
                    ]);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');

        return response()->json(['error' => 'Curso no encontrado en ninguna institución'], 404);
    }

    public function portalConfig(Request $request)
    {
        $companyDb = $request->get('company_db');

        if (!$companyDb) {
            return response()->json(['error' => 'company_db es requerido'], 400);
        }

        config(['database.connections.mysql.database' => $companyDb]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $configs = DB::table('academy_configs')->get()->keyBy('name');

        return response()->json([
            'logo' => $configs->get('logo')->value ?? '',
            'title' => $configs->get('portal_title')->value ?? 'Academy',
            'primary_color' => $configs->get('primary_color')->value ?? '#4F46E5',
            'secondary_color' => $configs->get('secondary_color')->value ?? '#7C3AED',
        ]);
    }

    public function listCourses(Request $request)
    {
        $companyDb = $request->get('company_db');

        if (!$companyDb) {
            return response()->json(['error' => 'company_db es requerido'], 400);
        }

        config(['database.connections.mysql.database' => $companyDb]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $courses = DB::table('academy_courses')
            ->where('is_published', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'level', 'cover_image']);

        return response()->json($courses);
    }
}
