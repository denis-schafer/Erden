<?php

namespace App\Packages\Academy\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Academy - Dashboard', 'slug' => 'academy-dashboard_read', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Ver Cursos', 'slug' => 'academy-courses_read', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Crear Cursos', 'slug' => 'academy-courses_create', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Editar Cursos', 'slug' => 'academy-courses_update', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Eliminar Cursos', 'slug' => 'academy-courses_delete', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Ver Módulos', 'slug' => 'academy-modules_read', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Crear Módulos', 'slug' => 'academy-modules_create', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Editar Módulos', 'slug' => 'academy-modules_update', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Eliminar Módulos', 'slug' => 'academy-modules_delete', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Ver Lecciones', 'slug' => 'academy-lessons_read', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Crear Lecciones', 'slug' => 'academy-lessons_create', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Editar Lecciones', 'slug' => 'academy-lessons_update', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Eliminar Lecciones', 'slug' => 'academy-lessons_delete', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Ver Alumnos', 'slug' => 'academy-students_read', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Crear Alumnos', 'slug' => 'academy-students_create', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Editar Alumnos', 'slug' => 'academy-students_update', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Eliminar Alumnos', 'slug' => 'academy-students_delete', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Ver Inscripciones', 'slug' => 'academy-enrollments_read', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Crear Inscripciones', 'slug' => 'academy-enrollments_create', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Eliminar Inscripciones', 'slug' => 'academy-enrollments_delete', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Ver Exámenes', 'slug' => 'academy-exams_read', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Crear Exámenes', 'slug' => 'academy-exams_create', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Editar Exámenes', 'slug' => 'academy-exams_update', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Eliminar Exámenes', 'slug' => 'academy-exams_delete', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Ver Notas', 'slug' => 'academy-grading_read', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Calificar', 'slug' => 'academy-grading_grade', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Configuración', 'slug' => 'academy-config_read', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Editar Config', 'slug' => 'academy-config_update', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Importar Cursos', 'slug' => 'academy-import_execute', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Academy - Documentación', 'slug' => 'academy-documentation_read', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $perm['slug']],
                $perm
            );
        }
    }
}
