<template>
    <div class="doc-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Documentación del Sistema</h4>
        </div>

        <div class="row g-3">
            <div class="col-md-3">
                <nav class="doc-nav card">
                    <div class="list-group list-group-flush">
                        <a v-for="section in sections" :key="section.id"
                           class="list-group-item list-group-item-action"
                           :class="{ active: activeSection === section.id }"
                           href="#"
                           @click.prevent="activeSection = section.id">
                            <i :class="section.icon" class="me-2"></i>
                            {{ section.label }}
                        </a>
                    </div>
                </nav>
            </div>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body doc-content">

                        <!-- PUESTA EN MARCHA -->
                        <div v-show="activeSection === 'setup'">
                            <h4 class="mb-3">Puesta en Marcha</h4>

                            <h5 class="mt-4">Requisitos del Servidor</h5>
                            <ul>
                                <li>PHP 8.1+ con extensiones: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML, MySQL, GD</li>
                                <li>MySQL 8.0+ o MariaDB 10.3+</li>
                                <li>Composer 2.x</li>
                                <li>Node.js 18+ y NPM</li>
                                <li>Servidor Web: Nginx o Apache con mod_rewrite</li>
                                <li>Git</li>
                            </ul>

                            <h5 class="mt-4">Instalación del Proyecto</h5>
                            <div class="bg-light p-3 rounded mb-3">
                                <code class="d-block">git clone https://github.com/tu-repo/erden.git</code>
                                <code class="d-block mt-1">cd erden</code>
                                <code class="d-block mt-1">composer install</code>
                                <code class="d-block mt-1">copy .env.example .env</code>
                                <code class="d-block mt-1">php artisan key:generate</code>
                                <code class="d-block mt-1">npm install && npm run build</code>
                            </div>
                            <p class="text-muted small">Configurar archivo <code>.env</code> con datos de conexión MySQL, luego ejecutar:</p>
                            <div class="bg-light p-3 rounded mb-3">
                                <code class="d-block">php artisan migration_all</code>
                            </div>

                            <h5 class="mt-4">Crear Compañía y Asignar POS</h5>
                            <ol>
                                <li>Ingresar al sistema con el usuario global admin</li>
                                <li>Ir a <strong>Admin → Compañías</strong></li>
                                <li>Crear una nueva compañía (se generará su DB automáticamente)</li>
                                <li>En <strong>Módulos</strong>, marcar <strong>POS</strong> y guardar</li>
                                <li>La DB de la compañía se creará con todas las tablas POS necesarias</li>
                            </ol>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Si la compañía ya existe y solo se agrega POS, el sistema instala las tablas y datos semilla sin afectar datos existentes.
                            </div>
                        </div>

                        <!-- CONFIGURACIÓN INICIAL -->
                        <div v-show="activeSection === 'config'">
                            <h4 class="mb-3">Configuración Inicial del POS</h4>

                            <ol>
                                <li>
                                    <strong>Ingresar al POS</strong> — Usar usuario <code>admin</code> / <code>$0deJulio</code>
                                </li>
                                <li class="mt-2">
                                    <strong>Ir a Configuración</strong> — Completar los datos del negocio:
                                    <ul class="mt-1">
                                        <li>Nombre del Negocio</li>
                                        <li>Dirección</li>
                                        <li>Teléfono</li>
                                        <li>NIT</li>
                                        <li>Título del Ticket</li>
                                    </ul>
                                </li>
                                <li class="mt-2">
                                    <strong>Modo de Impresión</strong> — Elegir según el caso:
                                    <div class="ms-3 mt-2">
                                        <div class="fw-semibold">VPS</div>
                                        <p class="mb-0 small text-muted">La impresión se realiza a través del Agente de Impresión (ErdenPrintAgent) instalado en la PC con la impresora térmica.</p>
                                        <div class="fw-semibold mt-2">Local</div>
                                        <p class="mb-0 small text-muted">La impresión se envía directamente a una impresora de tickets desde el navegador (solo compatible con servidores locales).</p>
                                    </div>
                                </li>
                                <li class="mt-2">
                                    <strong>Token MercadoPago</strong> — Ir a Configuración y presionar "Obtener Token MP" para vincular la cuenta de MercadoPago.
                                </li>
                            </ol>
                        </div>

                        <!-- AGENTE DE IMPRESIÓN -->
                        <div v-show="activeSection === 'agent'">
                            <h4 class="mb-3">Agente de Impresión (VPS)</h4>

                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Solo es necesario si el modo de impresión está configurado como <strong>VPS</strong>.
                            </div>

                            <h5>¿Qué es?</h5>
                            <p>
                                ErdenPrintAgent es un ejecutable portable que se instala en la PC que tiene conectada la impresora térmica.
                                Su función es consultar periódicamente el servidor VPS en busca de trabajos de impresión pendientes y enviarlos a la impresora local.
                            </p>

                            <h5 class="mt-4">Cómo funciona</h5>
                            <ol>
                                <li>El VPS recibe una orden y genera un trabajo de impresión</li>
                                <li>ErdenPrintAgent (en la PC del comercio) consulta cada 5 segundos al VPS</li>
                                <li>Si hay trabajos pendientes, los descarga y envía a la impresora térmica</li>
                                <li>Confirma al VPS que el trabajo se completó</li>
                            </ol>

                            <h5 class="mt-4">Instalación</h5>
                            <ol>
                                <li>En el POS, ir a <strong>Configuración → Agente de Impresión</strong></li>
                                <li>Copiar la <strong>Clave del Agente</strong> (UUID único por compañía)</li>
                                <li>Descargar <strong>ErdenPrintAgent.exe</strong></li>
                                <li>Ejecutar el .exe en la PC con la impresora térmica</li>
                                <li>Ingresar la URL del VPS y la Clave del Agente cuando lo solicite</li>
                                <li>Verificar que aparezca "Conectado" en la ventana del agente</li>
                            </ol>

                            <h5 class="mt-4">Configurar impresora en cada usuario</h5>
                            <ol>
                                <li>Ir a <strong>Usuarios</strong></li>
                                <li>Editar el usuario y configurar:
                                    <ul>
                                        <li><strong>IP de Impresora</strong> — IP de la PC donde corre el Agente</li>
                                        <li><strong>Puerto</strong> — Generalmente 9100</li>
                                        <li><strong>Tipo</strong> — raw (térmica) o network</li>
                                        <li><strong>Ancho</strong> — 80mm (58mm si es impresora chica)</li>
                                        <li>Activar <strong>Habilitar Impresión</strong></li>
                                    </ul>
                                </li>
                            </ol>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                El agente no necesita instalación, es un solo .exe portable. Se puede ejecutar como aplicación de inicio de Windows.
                            </div>
                        </div>

                        <!-- WEBHOOKS MP -->
                        <div v-show="activeSection === 'webhooks'">
                            <h4 class="mb-3">Webhooks MercadoPago</h4>

                            <h5>¿Qué son?</h5>
                            <p>
                                Los webhooks de MercadoPago permiten que el sistema reciba notificaciones automáticas cuando se realiza un pago (por ejemplo, un pago con QR).
                            </p>

                            <h5 class="mt-4">Entorno Local (con ngrok)</h5>
                            <ol>
                                <li>
                                    <strong>Descargar e instalar ngrok</strong> desde <a href="https://ngrok.com" target="_blank">ngrok.com</a>
                                </li>
                                <li class="mt-2">
                                    <strong>Exponer el servidor local:</strong>
                                    <div class="bg-light p-3 rounded mt-1">
                                        <code>ngrok http http://localhost:8000</code>
                                    </div>
                                    <p class="small text-muted mt-1">Esto genera una URL pública como <code>https://abc123.ngrok-free.app</code></p>
                                </li>
                                <li class="mt-2">
                                    <strong>Configurar URL de Callback en POS:</strong>
                                    Ir a <strong>Configuración</strong> y en "URL de Callback" pegar la URL de ngrok <strong>sin barra al final</strong>.
                                </li>
                                <li class="mt-2">
                                    <strong>Obtener Token OAuth:</strong>
                                    Presionar el botón "Obtener Token MP". Serás redirigido a MercadoPago para autorizar la conexión.
                                </li>
                                <li class="mt-2">
                                    <strong>Verificar:</strong>
                                    Ir a <strong>QR</strong> en el sidebar, generar un QR y escanearlo con la app de MercadoPago. El pago debe reflejarse automáticamente.
                                </li>
                            </ol>

                            <h5 class="mt-4">Entorno VPS (Producción)</h5>
                            <p>
                                En producción no se necesita ngrok. El VPS ya tiene HTTPS y dominio público. Solo se necesita:
                            </p>
                            <ol>
                                <li>Configurar la URL de callback con el dominio del VPS (ej: <code>https://erden.com.ar</code>)</li>
                                <li>Obtener el Token OAuth presionando el botón</li>
                            </ol>

                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                El botón "Obtener Token MP" se deshabilita si el token ya existe. Para renovarlo, presionar nuevamente (reemplaza el token anterior).
                            </div>
                        </div>

                        <!-- SINCRONIZACIÓN -->
                        <div v-show="activeSection === 'sync'">
                            <h4 class="mb-3">Sincronización Local ↔ VPS</h4>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                La sincronización solo se configura en el sistema <strong>Local</strong>. El VPS recibe los datos automáticamente.
                            </div>

                            <h5>Arquitectura</h5>
                            <p>
                                La sincronización es <strong>unidireccional: Local → VPS</strong>. Los cambios realizados en el local (productos, categorías, usuarios, órdenes) se replican al VPS.
                            </p>

                            <h5 class="mt-4">Configuración Inicial</h5>
                            <ol>
                                <li>En el Local, ir a <strong>Configuración → Sincronización Remota</strong></li>
                                <li>Ingresar la <strong>URL del Servidor Remoto</strong> (ej: <code>https://erden.com.ar</code>)</li>
                                <li>Ingresar la <strong>Clave de Sincronización</strong> (copiar desde VPS → Configuración → Agente de Impresión)</li>
                                <li>Guardar</li>
                                <li>Presionar <strong>Sincronización Inicial</strong> para encolar todos los registros existentes</li>
                                <li>En el servidor VPS, ejecutar <code>php artisan sync:push</code> (o esperar el cron automático cada 5 min)</li>
                            </ol>

                            <h5 class="mt-4">Sincronización Automática</h5>
                            <p>
                                Cada 5 minutos, el sistema Local ejecuta el comando <code>sync:push</code> vía cron, que envía los cambios pendientes al VPS en lotes de 100 registros.
                            </p>

                            <h5 class="mt-4">Forzar resincronización</h5>
                            <p>
                                Si se necesita reenviar todos los datos (ej: se corrigieron precios), presionar nuevamente <strong>Sincronización Inicial</strong>. Aparecerá un modal de confirmación y se reencolarán todos los registros.
                            </p>

                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Al reconectar el agente de impresión, asegurarse de que la clave (print_agent_key) coincida entre el VPS y el agente.
                            </div>
                        </div>

                        <!-- ARQUITECTURA -->
                        <div v-show="activeSection === 'architecture'">
                            <h4 class="mb-3">Arquitectura del Sistema</h4>

                            <h5>Multi-tenant</h5>
                            <p>
                                El sistema utiliza una arquitectura multi-tenant con base de datos separada por compañía:
                            </p>
                            <ul>
                                <li><strong>Base de datos padre (erden)</strong> — Almacena: compañías, módulos globales, usuarios globales, roles globales, asignación de módulos por compañía</li>
                                <li><strong>Base de datos hija (erden_xxx)</strong> — Una por compañía. Almacena: productos, categorías, usuarios POS, órdenes, configuración POS</li>
                            </ul>

                            <h5 class="mt-4">Stack Tecnológico</h5>
                            <table class="table table-sm table-bordered">
                                <tbody>
                                    <tr><td><strong>Backend</strong></td><td>Laravel 11 / PHP 8.2</td></tr>
                                    <tr><td><strong>Frontend</strong></td><td>Vue 3 (Composition API) + Pinia + Bootstrap 5</td></tr>
                                    <tr><td><strong>Base de datos</strong></td><td>MySQL 8+</td></tr>
                                    <tr><td><strong>Tiempo real</strong></td><td>Laravel Reverb + WebSockets (Echo)</td></tr>
                                    <tr><td><strong>Impresión</strong></td><td>ErdenPrintAgent (Python → .exe portable)</td></tr>
                                    <tr><td><strong>Pagos</strong></td><td>MercadoPago API (OAuth + QR)</td></tr>
                                    <tr><td><strong>Sincronización</strong></td><td>Cola de archivos JSON + HTTP Push</td></tr>
                                </tbody>
                            </table>

                            <h5 class="mt-4">sync_id</h5>
                            <p>
                                Cada registro en las tablas POS tiene un campo <code>sync_id</code> (UUID) que permite:
                            </p>
                            <ul>
                                <li>Identificar de forma única cada registro entre Local y VPS</li>
                                <li>Evitar colisiones de IDs auto-incrementales</li>
                                <li>Resolver relaciones entre tablas al sincronizar</li>
                            </ul>

                            <h5 class="mt-4">Esquema de Sincronización</h5>
                            <div class="bg-light p-3 rounded">
                                <pre class="mb-0 small"><code>LOCAL (Cliente)
  │
  ├── CRUD (Productos, Categorías, Usuarios, Órdenes)
  │     └── Genera archivo .json en storage/sync/queue/
  │
  ├── php artisan sync:push (cada 5 min vía cron)
  │     └── HTTP POST → VPS /pos/sync/push
  │           └── Upsert por sync_id (o fallback por nombre)
  │
  └── Sincronización Inicial (Backfill)
        └── Asigna sync_id a todos los registros y los encola</code></pre>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const sections = [
    { id: 'setup', label: 'Puesta en Marcha', icon: 'bi-rocket-takeoff' },
    { id: 'config', label: 'Configuración Inicial', icon: 'bi-sliders' },
    { id: 'agent', label: 'Agente de Impresión', icon: 'bi-printer' },
    { id: 'webhooks', label: 'Webhooks MP', icon: 'bi-currency-dollar' },
    { id: 'sync', label: 'Sincronización', icon: 'bi-arrow-repeat' },
    { id: 'architecture', label: 'Arquitectura', icon: 'bi-diagram-3' },
];

const activeSection = ref('setup');
</script>

<style scoped>
.doc-container {
    height: 100%;
    overflow-y: auto;
    padding: 1rem;
}
.doc-nav {
    position: sticky;
    top: 0;
}
.doc-content {
    min-height: 400px;
}
.doc-content h4 {
    border-bottom: 2px solid #0d6efd;
    padding-bottom: 0.5rem;
}
.doc-content h5 {
    color: #495057;
    margin-top: 1.5rem;
}
.doc-content code {
    background-color: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    font-size: 0.875rem;
}
.doc-content .bg-light code {
    display: block;
    padding: 0.25rem 0;
    background: transparent;
}
.doc-content ol li {
    margin-bottom: 0.5rem;
}
.doc-content ul li {
    margin-bottom: 0.25rem;
}
.pre {
    white-space: pre-wrap;
}
</style>
