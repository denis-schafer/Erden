<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GuardarCambios extends Command
{
    protected $signature = 'guardar:cambios';
    protected $description = 'Publicar una nueva entrada de novedades';

    public function handle()
    {
        $modules = ['quota', 'hairsalon', 'pos', 'general'];

        $this->info('Módulos disponibles:');
        foreach ($modules as $i => $m) {
            $this->line("  [" . ($i + 1) . "] {$m}");
        }

        $choice = $this->ask('¿Módulo? (número)');

        if (!is_numeric($choice) || !isset($modules[$choice - 1])) {
            $this->error('Selección inválida. Ingresá un número del 1 al ' . count($modules));
            return 1;
        }

        $module = $modules[$choice - 1];

        $title = $this->ask('¿Título?');

        if (!$title) {
            $this->error('El título es obligatorio');
            return 1;
        }

        $content = $this->ask('¿Contenido?');

        if (!$content) {
            $this->error('El contenido es obligatorio');
            return 1;
        }

        $id = DB::table('changelog_entries')->insertGetId([
            'module' => $module,
            'title' => $title,
            'content' => $content,
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("✅ Novedad publicada en {$module} (ID: {$id})");

        return 0;
    }
}
