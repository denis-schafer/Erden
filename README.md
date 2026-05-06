# Erden - Sistema de Gestión POS

Sistema de punto de venta (POS) con arquitectura multi-empresa, construido con Laravel y Vue.js.

## 🚀 Características Principales

- **Login multi-empresa** con detección automática de usuario global
- **Módulos POS**: Caja, Categorías, Productos, Órdenes, Usuarios, Estadísticas, Log
- **Estadísticas** con gráficos interactivos (Line, Doughnut) y exportación a Excel
- **Sistema de Logs** por módulo con filtros y seguimiento de cambios
- **Configuración de impresoras** por usuario con habilitación selectiva
- **Integración con MercadoPago** para pagos vía QR
- **WebSocket en tiempo real** para actualización de órdenes

## 📋 Módulos del Sistema

### Admin (Global)
- **Admin Módulos**: Asignación de módulos a empresas
- **Admin Compañías**: Gestión de empresas y bases de datos hijas
- **Usuarios**: Gestión de usuarios globales
- **Roles**: Configuración de roles y permisos

### POS (Punto de Venta)
- **Dashboard**: Panel principal con resumen de ventas
- **Caja**: Punto de venta con gestión de órdenes
- **Categorías**: Gestión de categorías de productos
- **Productos**: Gestión de productos con habilitación/deshabilitación
- **Órdenes**: Lista de órdenes con filtros y WebSocket
- **Usuarios POS**: Gestión de usuarios por empresa
- **Configuración**: Configuración del sistema POS
- **QR**: Generación de códigos QR para pagos
- **Estadísticas**: Gráficos de ventas, productos y tickets promedio
- **Log**: Registro de actividad del sistema

## 🔧 Instalación

### Requisitos
- PHP 8.1+
- Composer
- Node.js 16+
- MySQL/MariaDB

### Pasos

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/denis-schafer/Erden.git
   cd Erden
   ```

2. **Instalar dependencias de PHP**
   ```bash
   composer install
   ```

3. **Instalar dependencias de Node**
   ```bash
   npm install
   ```

4. **Configurar entorno**
   ```bash
   cp .env.example .env
   # Editar .env con tus configuraciones de base de datos
   ```

5. **Generar clave de aplicación**
   ```bash
   php artisan key:generate
   ```

6. **Ejecutar migraciones y seeders**
   ```bash
   php artisan migration_all
   ```

7. **Compilar assets**
   ```bash
   npm run build
   ```

8. **Iniciar servidor**
   ```bash
   php artisan serve
   ```

9. **Iniciar WebSocket (opcional, para actualizaciones en tiempo real)**
   ```bash
   php artisan reverb:start
   ```

## 👤 Usuario por Defecto

Después de ejecutar `migration_all`, se crea un usuario administrador global:
- **Usuario**: admin
- **Contraseña**: password
- **Rol**: admin-global

## 🏢 Estructura Multi-Empresa

1. Crear empresa desde el módulo "Admin Compañías"
2. Asignar módulos a la empresa desde "Admin Módulos"
3. Crear usuarios locales para la empresa
4. El login detecta automáticamente si el usuario es global o local

## 📊 Tecnologías Utilizadas

- **Backend**: Laravel 11.x
- **Frontend**: Vue.js 3, Bootstrap 5, Vite
- **Gráficos**: Chart.js (vue-chartjs)
- **Exportación**: SheetJS (xlsx)
- **Tiempo real**: Laravel Reverb / Pusher
- **Pagos**: MercadoPago SDK

## 📄 Licencia

Este proyecto está bajo la licencia MIT. Ver el archivo [LICENSE](LICENSE) para más detalles.

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:
1. Haz fork del proyecto
2. Crea una rama para tu feature (`git checkout -b feature/nueva-feature`)
3. Haz commit de tus cambios (`git commit -m 'Agrega nueva feature'`)
4. Haz push a la rama (`git push origin feature/nueva-feature`)
5. Abre un Pull Request

## 📞 Contacto

- **GitHub**: [@denis-schafer](https://github.com/denis-schafer)
- **Email**: denis.schafer@gmail.com
