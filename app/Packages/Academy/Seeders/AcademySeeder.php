<?php

namespace App\Packages\Academy\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcademySeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ConfigSeeder::class);

        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if (!$adminRole) {
            $adminRoleId = DB::table('roles')->insertGetId([
                'name' => 'admin',
                'slug' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $adminRoleId = $adminRole->id;
        }

        $this->call(PermissionSeeder::class);

        $adminPermissions = DB::table('permissions')
            ->where('slug', 'like', 'academy-%')
            ->pluck('id')
            ->toArray();

        foreach ($adminPermissions as $permId) {
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $adminRoleId, 'permission_id' => $permId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->seedModules();

        // Admin user already created by other seeders - not creating a new one
    }

    protected function seedModules(): void
    {
        $modules = [
            ['name' => 'Dashboard', 'route' => 'academy-dashboard', 'icon' => 'bi-speedometer2', 'is_special' => true, 'order' => 1, 'package' => 'academy', 'description' => 'Dashboard de Academy'],
            ['name' => 'Cursos', 'route' => 'academy-courses', 'icon' => 'bi-book', 'is_special' => false, 'order' => 2, 'package' => 'academy', 'description' => 'Gestión de cursos'],
            ['name' => 'Alumnos', 'route' => 'academy-students', 'icon' => 'bi-people', 'is_special' => false, 'order' => 3, 'package' => 'academy', 'description' => 'Gestión de alumnos'],
            ['name' => 'Inscripciones', 'route' => 'academy-enrollments', 'icon' => 'bi-pencil-square', 'is_special' => false, 'order' => 4, 'package' => 'academy', 'description' => 'Gestión de inscripciones'],
            ['name' => 'Exámenes', 'route' => 'academy-exams', 'icon' => 'bi-question-circle', 'is_special' => false, 'order' => 5, 'package' => 'academy', 'description' => 'Gestión de exámenes'],
            ['name' => 'Calificaciones', 'route' => 'academy-grading', 'icon' => 'bi-check2-square', 'is_special' => false, 'order' => 6, 'package' => 'academy', 'description' => 'Ver calificaciones'],
            ['name' => 'Importar Curso', 'route' => 'academy-import', 'icon' => 'bi-upload', 'is_special' => false, 'order' => 7, 'package' => 'academy', 'description' => 'Importar cursos desde archivo'],
            ['name' => 'Documentación', 'route' => 'academy-documentation', 'icon' => 'bi-file-text', 'is_special' => false, 'order' => 8, 'package' => 'academy', 'description' => 'Documentación del módulo'],
            ['name' => 'Configuración', 'route' => 'academy-config', 'icon' => 'bi-sliders', 'is_special' => false, 'order' => 9, 'package' => 'academy', 'description' => 'Configuración de Academy'],
        ];

        foreach ($modules as $module) {
            try {
                DB::table('modules')->updateOrInsert(
                    ['route' => $module['route']],
                    array_merge($module, ['created_at' => now(), 'updated_at' => now()])
                );
            } catch (\Exception $e) {
                Log::error('[AcademySeeder] Error seeding module ' . $module['route'] . ': ' . $e->getMessage());
            }
        }
    }
}
