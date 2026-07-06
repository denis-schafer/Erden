# Reglas para el agente

1. **Producción primero**: Este proyecto tiene clientes en producción. Ninguna modificación puede eliminar, truncar o sobrescribir datos existentes a menos que se solicite explícitamente. Los seeders y migraciones deben preservar la información existente.

2. **Idioma**: Todas las respuestas deben estar en español.

3. **Publicar novedades (changelog)**: El usuario puede pedir publicar cambios con el atajo `guardar:`. El agente debe ejecutar un INSERT directo en la tabla `changelog_entries` de la base de datos `erden` (padre).

   Formato: `guardar: <módulo> | <título> | <contenido>`
   
   Módulos válidos: `quota`, `hairsalon`, `pos`, `general`
   
   Si recibe `guardar:` sin parámetros o `guardar: help`, debe responder con el formato y los módulos válidos.

   El agente debe ejecutar vía bash:
   ```
   mysql -u root erden -e "INSERT INTO changelog_entries (module, title, content, is_published, created_at, updated_at) VALUES ('<modulo>', '<titulo>', '<contenido>', 1, NOW(), NOW())"
   ```
   Ruta MySQL: `C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe`
   Password: `20dejulio`
   Comando exacto: `& "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root -p20dejulio erden -e "INSERT..."`

4. **Migraciones**: Las migraciones de paquetes van en `app/Packages/{Package}/Migrations/` y se ejecutan automáticamente en los 3 flujos: `migration_all`, `module:install` (CLI) y panel admin (`CompanyModuleController`). Las migraciones globales de DB hija van en `database/migrations/` y se agregan a `$childMigrations` en `MigrationAll.php`. Las migraciones de DB padre van en `database/migrations/` y se agregan a `$parentMigrations`.
