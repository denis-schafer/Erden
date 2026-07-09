<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AcademyDocumentationController extends Controller
{
    public function index()
    {
        return response()->json([
            'sections' => [
                [
                    'id' => 'introduccion',
                    'title' => '1. Introduccion',
                    'content' => '<p><strong>Academy</strong> es el modulo de gestion de cursos y aprendizaje. Permite crear cursos con contenido teorico-practico, gestionar alumnos, crear examenes de opcion multiple y evaluar resultados.</p>
                    <p>Disenado para usarse desde cualquier dispositivo, incluyendo celulares, ideal para ninos desde 12 anos.</p>',
                ],
                [
                    'id' => 'crear-curso-manual',
                    'title' => '2. Crear un curso manualmente',
                    'content' => '<ol>
                        <li><strong>Academy → Cursos</strong> en el panel lateral.</li>
                        <li>Hacer clic en <strong>"Nuevo Curso"</strong>.</li>
                        <li>Completar: nombre, descripcion, nivel y portada (URL de imagen).</li>
                        <li>Guardar el curso.</li>
                        <li>Dentro del curso, agregar <strong>Modulos</strong> (secciones principales).</li>
                        <li>Dentro de cada modulo, agregar <strong>Lecciones</strong> con contenido.</li>
                        <li>Usar el editor <strong>Quill</strong> para dar formato al contenido (negrita, listas, links, bloques de codigo).</li>
                        <li>Para videos de YouTube, pegar la URL embed en el campo "Video URL".</li>
                    </ol>',
                ],
                [
                    'id' => 'importar-curso',
                    'title' => '3. Importar curso desde archivo .txt',
                    'content' => '<h4>Formato del archivo</h4>
                    <p>El archivo <strong>.txt</strong> usa marcadores para definir la estructura del curso:</p>
                    <ul>
                        <li><code>==COURSE==</code> — Inicio del curso (nombre: + slug: + description: + level:)</li>
                        <li><code>==MODULE==</code> — Inicio de un modulo (name: + description: + order:)</li>
                        <li><code>==LESSON==</code> — Inicio de una leccion (name: + order: + content:)</li>
                        <li><code>==EXAM==</code> — Inicio de un examen (name: + description: + passing_score: + max_attempts:)</li>
                        <li><code>==QUESTION==</code> — Inicio de una pregunta (text: + points: + type:)</li>
                        <li><code>==OPTION==</code> — Inicio de una opcion (text: + is_correct: 1/0)</li>
                    </ul>
                    <p>El contenido de las lecciones va en <strong>HTML</strong> despues de "content:".</p>
                    <h4>Pasos para importar</h4>
                    <ol>
                        <li>Descargar la <strong>plantilla</strong> desde Academy → Importar Curso → "Descargar plantilla".</li>
                        <li>Editarla en cualquier editor de texto.</li>
                        <li>Ir a <strong>Academy → Importar Curso</strong>.</li>
                        <li>Seleccionar el archivo .txt y hacer clic en "Importar".</li>
                    </ol>
                    <div class="alert alert-info">
                        <strong>Nota:</strong> El formato .docx (Word) esta disponible pero el .txt es mas confiable para la importacion.
                    </div>',
                ],
                [
                    'id' => 'editar-contenido',
                    'title' => '4. Editar contenido de las lecciones',
                    'content' => '<ol>
                        <li><strong>Academy → Cursos</strong> → hacer clic en el curso.</li>
                        <li>Se abre la vista de <strong>Modulos</strong> → hacer clic en el modulo.</li>
                        <li>Se abre la lista de <strong>Lecciones</strong> → hacer clic en el lapiz ✏️ para editar.</li>
                        <li>Se abre el editor visual <strong>Quill</strong> con opciones de formato.</li>
                        <li>Para insertar una <strong>imagen</strong>: usar el boton de imagen (🔗) y pegar la URL.</li>
                        <li>Para un <strong>video de YouTube</strong>: pegar la URL embed (https://www.youtube.com/embed/VIDEO_ID) en el campo "Video URL".</li>
                        <li>Para <strong>codigo</strong>: usar el boton de bloque de codigo ({"<"} / {">"}).</li>
                        <li>Hacer clic en <strong>"Guardar"</strong>.</li>
                    </ol>
                    <p>Si necesitas editar el HTML directamente, puedes hacerlo en el textarea debajo del editor visual.</p>',
                ],
                [
                    'id' => 'gestion-alumnos',
                    'title' => '5. Gestion de alumnos',
                    'content' => '<ol>
                        <li>Ir a <strong>Academy → Alumnos</strong>.</li>
                        <li>Crear un alumno: nombre, apellido, DNI (unico), email opcional.</li>
                        <li>La <strong>contrasena inicial</strong> es el numero de <strong>DNI</strong> del alumno.</li>
                        <li>Ir a <strong>Academy → Inscripciones</strong> para asignar alumnos a cursos.</li>
                        <li>Seleccionar el curso y los alumnos a inscribir.</li>
                    </ol>',
                ],
                [
                    'id' => 'examenes',
                    'title' => '6. Examenes y evaluacion',
                    'content' => '<ol>
                        <li>Ir a <strong>Academy → Examene</strong>.</li>
                        <li>Crear un examen: nombre, descripcion, puntaje de aprobacion (ej: 6), intentos maximos.</li>
                        <li>Agregar preguntas de <strong>opcion multiple</strong> con al menos 2 opciones.</li>
                        <li>Marcar cual es la opcion correcta.</li>
                        <li>Los alumnos rinden el examen desde el portal.</li>
                        <li>Ir a <strong>Academy → Calificaciones</strong> para revisar resultados.</li>
                        <li>Las calificaciones se calculan automaticamente.</li>
                    </ol>',
                ],
                [
                    'id' => 'portal-alumno',
                    'title' => '7. Portal del alumno',
                    'content' => '<h4>Acceso</h4>
                    <p>Los alumnos acceden desde la URL publica del sistema:</p>
                    <ul>
                        <li><code>/curso</code> — Pantalla de inicio: buscar institucion → seleccionar curso → iniciar sesion con DNI</li>
                        <li><code>/curso/{slug-del-curso}</code> — Selecciona el curso automaticamente y pide DNI</li>
                        <li><code>/curso/{slug-del-curso}/{dni}</code> — Auto-login directo (ej: /curso/introduccion-a-la-programacion/12345678)</li>
                    </ul>
                    <h4>Funcionalidades del alumno</h4>
                    <ul>
                        <li>Ver sus cursos con barra de progreso</li>
                        <li>Navegar modulos y lecciones</li>
                        <li>Marcar lecciones como completadas</li>
                        <li>Rendir examenes (una pregunta por vez en mobile)</li>
                        <li>Ver resultados de examenes y respuestas correctas</li>
                        <li>Cambiar su contrasena</li>
                    </ul>',
                ],
                [
                    'id' => 'configuracion',
                    'title' => '8. Configuracion del modulo',
                    'content' => '<p>Desde <strong>Academy → Configuracion</strong> se pueden ajustar:</p>
                    <ul>
                        <li><strong>Color Primario:</strong> color del sidebar y encabezados</li>
                        <li><strong>Color Secundario:</strong> color de acentos</li>
                        <li><strong>Logo del Portal:</strong> imagen que se muestra en el login del alumno</li>
                        <li><strong>Imagen de Fondo:</strong> fondo del dashboard</li>
                        <li><strong>Arrastrar modulos del menu:</strong> activa el drag & drop en el sidebar</li>
                        <li><strong>Intentos maximos por examen:</strong> limite de reintentos</li>
                    </ul>',
                ],
                [
                    'id' => 'faq',
                    'title' => '9. FAQ y solucion de problemas',
                    'content' => '<div class="accordion">
                        <div class="accordion-item">
                            <h5>¿Por que no veo el modulo Academy en el menu?</h5>
                            <p>Asegurate de que el modulo este asignado a la compania desde <strong>Admin Companias → Modulos</strong>.</p>
                        </div>
                        <div class="accordion-item">
                            <h5>¿Como restablezco la contrasena de un alumno?</h5>
                            <p>Desde <strong>Academy → Alumnos</strong>, buscar el alumno y hacer clic en "Restablecer contrasena". Se restablecera a su DNI.</p>
                        </div>
                        <div class="accordion-item">
                            <h5>El alumno no puede acceder al portal</h5>
                            <p>Verificar: 1) Que el alumno este activo, 2) Que este inscrito al curso, 3) Que el curso este publicado.</p>
                        </div>
                        <div class="accordion-item">
                            <h5>¿Los examenes se corrigen solos?</h5>
                            <p>Si, las preguntas de opcion multiple se corrigen automaticamente al enviar el examen.</p>
                        </div>
                        <div class="accordion-item">
                            <h5>¿Como importo un curso?</h5>
                            <p>Descarga la plantilla desde <strong>Academy → Importar Curso</strong>, editala en cualquier editor de texto y importala. El formato es .txt con marcadores (==COURSE==, ==MODULE==, etc.).</p>
                        </div>
                        <div class="accordion-item">
                            <h5>¿Como agrego imagenes a una leccion?</h5>
                            <p>Desde <strong>Academy → Lecciones</strong>, edita la leccion y usa el editor visual Quill. El boton de imagen te permite insertar una URL de imagen.</p>
                        </div>
                    </div>',
                ],
            ],
        ]);
    }
}
