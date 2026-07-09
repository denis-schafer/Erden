<?php

namespace App\Packages\Academy\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademyModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Dashboard',
                'route' => 'academy-dashboard',
                'icon' => 'bi-speedometer2',
                'description' => 'Dashboard de Academy',
                'is_special' => true,
                'order' => 1,
                'package' => 'academy',
            ],
            [
                'name' => 'Cursos',
                'route' => 'academy-courses',
                'icon' => 'bi-book',
                'description' => 'Gestión de cursos',
                'is_special' => false,
                'order' => 2,
                'package' => 'academy',
            ],
            [
                'name' => 'Alumnos',
                'route' => 'academy-students',
                'icon' => 'bi-people',
                'description' => 'Gestión de alumnos',
                'is_special' => false,
                'order' => 3,
                'package' => 'academy',
            ],
            [
                'name' => 'Inscripciones',
                'route' => 'academy-enrollments',
                'icon' => 'bi-pencil-square',
                'description' => 'Gestión de inscripciones',
                'is_special' => false,
                'order' => 4,
                'package' => 'academy',
            ],
            [
                'name' => 'Exámenes',
                'route' => 'academy-exams',
                'icon' => 'bi-question-circle',
                'description' => 'Gestión de exámenes',
                'is_special' => false,
                'order' => 5,
                'package' => 'academy',
            ],
            [
                'name' => 'Calificaciones',
                'route' => 'academy-grading',
                'icon' => 'bi-check2-square',
                'description' => 'Ver calificaciones',
                'is_special' => false,
                'order' => 6,
                'package' => 'academy',
            ],
            [
                'name' => 'Importar Curso',
                'route' => 'academy-import',
                'icon' => 'bi-upload',
                'description' => 'Importar cursos desde archivo',
                'is_special' => false,
                'order' => 7,
                'package' => 'academy',
            ],
            [
                'name' => 'Documentación',
                'route' => 'academy-documentation',
                'icon' => 'bi-file-text',
                'description' => 'Documentación del módulo',
                'is_special' => false,
                'order' => 8,
                'package' => 'academy',
            ],
            [
                'name' => 'Configuración',
                'route' => 'academy-config',
                'icon' => 'bi-sliders',
                'description' => 'Configuración de Academy',
                'is_special' => false,
                'order' => 9,
                'package' => 'academy',
            ],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['route' => $module['route']],
                array_merge($module, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
