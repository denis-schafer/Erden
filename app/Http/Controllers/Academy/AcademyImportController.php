<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AcademyImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:docx,txt|max:10240',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        if ($extension === 'docx') {
            return $this->importFromDocx($file);
        }

        return $this->importFromTxt($file);
    }

    private function sanitizeUtf8($text)
    {
        if ($text === null || $text === '') return '';
        $result = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        return $result !== false ? $result : '';
    }

    protected function importFromDocx($file)
    {
        if (!class_exists('\\PhpOffice\\PhpWord\\IOFactory')) {
            return response()->json([
                'message' => 'La librería PhpOffice/PhpWord no está instalada. Ejecute: composer require phpoffice/phpword',
            ], 500);
        }

        try {
            $tempPath = $file->getPathname();
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($tempPath);

            $courseData = [];
            $currentModule = null;
            $currentLesson = null;
            $currentExam = null;
            $currentQuestion = null;

            $imageStoragePath = 'public/academy/imported';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\Title) {
                        $level = $element->getDepth();
                        $text = trim($this->sanitizeUtf8(strip_tags($element->getText())));

                        if ($level == 1) {
                            if ($currentExam && $currentQuestion) {
                                $courseData['exams'][] = $currentExam;
                                $currentQuestion = null;
                            }
                            if ($currentExam) {
                                $courseData['exams'][] = $currentExam;
                                $currentExam = null;
                            }
                            if (preg_match('/^\[EXAM\]\s*(.+)/i', $text, $m)) {
                                $currentExam = ['name' => trim($m[1]), 'questions' => []];
                            } else {
                                $courseData['name'] = $text;
                                $courseData['slug'] = Str::slug($text) . '-' . Str::random(5);
                            }
                        } elseif ($level == 2) {
                            if ($currentLesson && $currentModule) {
                                $currentModule['lessons'][] = $currentLesson;
                                $currentLesson = null;
                            }
                            if (preg_match('/^\[EXAM\]\s*(.+)/i', $text, $m)) {
                                if ($currentExam) {
                                    $courseData['exams'][] = $currentExam;
                                }
                                $currentExam = ['name' => trim($m[1]), 'module_name' => $currentModule['name'] ?? null, 'questions' => []];
                            } else {
                                if ($currentModule && !empty($currentModule['lessons'])) {
                                    $courseData['modules'][] = $currentModule;
                                }
                                $currentModule = ['name' => $text, 'lessons' => []];
                            }
                        } elseif ($level == 3) {
                            if ($currentLesson && $currentModule) {
                                $currentModule['lessons'][] = $currentLesson;
                            }
                            $currentLesson = ['name' => $text, 'content' => '', 'video_url' => null];

                            if (preg_match('/^\[QUESTION\]\s*(.+)/i', $text, $m)) {
                                if ($currentExam) {
                                    $currentQuestion = ['text' => trim($m[1]), 'type' => 'multiple_choice', 'points' => 1, 'options' => []];
                                }
                                $currentLesson = null;
                            }
                        }
                    } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        $htmlContent = $this->textRunToHtml($element, $imageStoragePath);

                        if ($currentLesson) {
                            $currentLesson['content'] .= $htmlContent;

                            $youtubeRegex = '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/';
                            if (preg_match($youtubeRegex, $htmlContent, $matches)) {
                                $currentLesson['video_url'] = 'https://www.youtube.com/embed/' . $matches[1];
                            }
                        }

                        $cleanHtml = $this->sanitizeUtf8(strip_tags($htmlContent));

                        if (preg_match('/^\[QUESTION\]\s*(.+)/i', $cleanHtml, $m)) {
                            if ($currentExam) {
                                if ($currentQuestion && !empty($currentQuestion['options'])) {
                                    $currentExam['questions'][] = $currentQuestion;
                                }
                                $currentQuestion = ['text' => trim($m[1]), 'type' => 'multiple_choice', 'points' => 1, 'options' => []];
                            }
                        }

                        if (preg_match('/^\[OPTION\]\s*(.+?)(\s*[✓]?)$/i', $cleanHtml, $m)) {
                            if ($currentQuestion) {
                                $isCorrect = strpos($m[0], '✓') !== false;
                                $currentQuestion['options'][] = [
                                    'text' => trim($m[1]),
                                    'is_correct' => $isCorrect,
                                ];
                            }
                        }
                    } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                        $tableHtml = $this->parseTable($element);
                        if ($currentLesson) {
                            $currentLesson['content'] .= $tableHtml;
                        }
                    }
                }
            }

            if ($currentLesson && $currentModule) {
                $currentModule['lessons'][] = $currentLesson;
            }
            if ($currentModule && !empty($currentModule['lessons'])) {
                $courseData['modules'][] = $currentModule;
            }
            if ($currentQuestion && $currentExam && !empty($currentQuestion['options'])) {
                $currentExam['questions'][] = $currentQuestion;
            }
            if ($currentExam && !empty($currentExam['questions'])) {
                $courseData['exams'][] = $currentExam;
            }

            if (empty($courseData['name'])) {
                return response()->json(['message' => 'No se encontró un título de curso (Heading 1) en el documento'], 400);
            }

            return $this->saveCourse($courseData);

        } catch (\Exception $e) {
            $message = 'Error al procesar el archivo: ' . $e->getMessage() . ' (line: ' . $e->getLine() . ', file: ' . basename($e->getFile()) . ')';
            \Log::error('[AcademyImport] ' . $message . "\n" . $e->getTraceAsString());
            return response()->json([
                'message' => $message,
            ], 500);
        }
    }

    protected function importFromTxt($file)
    {
        $content = file_get_contents($file->getPathname());
        $lines = explode("\n", $content);

        $courseData = [];
        $currentModule = null;
        $currentLesson = null;
        $currentExam = null;
        $currentQuestion = null;
        $currentOption = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if ($line === '==COURSE==') {
                continue;
            }

            if (str_starts_with($line, '==COURSE==') || str_starts_with($line, '==MODULE==') || str_starts_with($line, '==LESSON==') || str_starts_with($line, '==EXAM==') || str_starts_with($line, '==QUESTION==') || str_starts_with($line, '==OPTION==')) {

                if ($currentOption && $currentQuestion) {
                    $currentQuestion['options'][] = $currentOption;
                    $currentOption = null;
                }
                if ($currentQuestion && $currentExam) {
                    $currentExam['questions'][] = $currentQuestion;
                    $currentQuestion = null;
                }
                if ($currentExam && $line === '==COURSE==') {
                    $courseData['exams'][] = $currentExam;
                    $currentExam = null;
                }
                if ($currentLesson && $currentModule) {
                    $currentModule['lessons'][] = $currentLesson;
                    $currentLesson = null;
                }
                if ($currentModule && ($line === '==COURSE==')) {
                    $courseData['modules'][] = $currentModule;
                    $currentModule = null;
                }

                $currentSection = trim($line, '==');
                continue;
            }

            if (strpos($line, ':') === false) continue;

            list($key, $value) = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (str_starts_with($line, '==COURSE==') || str_starts_with($line, '==MODULE==') || str_starts_with($line, '==LESSON==') || str_starts_with($line, '==EXAM==') || str_starts_with($line, '==QUESTION==') || str_starts_with($line, '==OPTION==')) {
                continue;
            }

            $currentSection = '';
            if (preg_match('/^==(\w+)==/', $line, $m)) {
                $currentSection = $m[1];
                continue;
            }

            if (preg_match('/^==(\w+)==$/', $line, $m)) {
                $currentSection = $m[1];
                continue;
            }

            if (!preg_match('/^==(\w+)==/', $line)) {
                $sectionMatch = null;
                foreach (['COURSE', 'MODULE', 'LESSON', 'EXAM', 'QUESTION', 'OPTION'] as $s) {
                    if (str_starts_with($line, "=={$s}==")) {
                        $sectionMatch = $s;
                        break;
                    }
                }

                if (!$sectionMatch) {
                    if ($currentLesson) {
                        $currentLesson['content'] .= $line . "\n";
                    }
                    continue;
                }

                switch ($sectionMatch) {
                    case 'MODULE':
                        $currentModule = ['name' => '', 'lessons' => []];
                        break;
                    case 'LESSON':
                        $currentLesson = ['name' => '', 'content' => '', 'video_url' => null];
                        break;
                    case 'EXAM':
                        $currentExam = ['name' => '', 'questions' => []];
                        break;
                    case 'QUESTION':
                        $currentQuestion = ['text' => '', 'type' => 'multiple_choice', 'points' => 1, 'options' => []];
                        break;
                    case 'OPTION':
                        $currentOption = ['text' => '', 'is_correct' => false];
                        break;
                }
                continue;
            }

            if ($currentLesson && !str_contains($line, ':')) {
                $currentLesson['content'] .= $line . "\n";
                continue;
            }

            if (!str_contains($line, ':')) continue;

            $colonPos = strpos($line, ':');
            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));

            if ($currentSection === 'COURSE' || preg_match('/^==COURSE==/', $line)) {
                // handled above
            }

            // Determine current section from recent markers
            // Re-parse with cleaner approach
        }

        // Clean up remaining objects
        if ($currentOption && $currentQuestion) {
            $currentQuestion['options'][] = $currentOption;
        }
        if ($currentQuestion && $currentExam) {
            $currentExam['questions'][] = $currentQuestion;
        }
        if ($currentExam) {
            $courseData['exams'][] = $currentExam;
        }
        if ($currentLesson && $currentModule) {
            $currentModule['lessons'][] = $currentLesson;
        }
        if ($currentModule) {
            $courseData['modules'][] = $currentModule;
        }

        // Parse the file properly
        $courseData = $this->parseTxtFile($content);

        if (empty($courseData['name'])) {
            return response()->json(['message' => 'El archivo .txt no tiene un formato válido. Use la plantilla de documentación.'], 400);
        }

        return $this->saveCourse($courseData);
    }

    protected function parseTxtFile($content)
    {
        $data = ['name' => '', 'slug' => '', 'description' => '', 'level' => 'beginner', 'modules' => [], 'exams' => []];
        $currentModule = null;
        $currentLesson = null;
        $currentExam = null;
        $currentQuestion = null;
        $currentOption = null;
        $inContent = false;

        $lines = explode("\n", $content);
        $state = 'idle';
        $contentBuffer = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '==COURSE==') {
                $state = 'course';
                $inContent = false;
                continue;
            }
            if ($trimmed === '==MODULE==') {
                if ($currentLesson && $currentModule) {
                    if ($currentLesson['content'] === '' && $contentBuffer) {
                        $currentLesson['content'] = $contentBuffer;
                    }
                    $currentModule['lessons'][] = $currentLesson;
                    $contentBuffer = '';
                }
                if ($currentModule) {
                    $data['modules'][] = $currentModule;
                }
                $currentModule = ['name' => '', 'description' => '', 'order' => count($data['modules']) + 1, 'lessons' => []];
                $state = 'module';
                $inContent = false;
                continue;
            }
            if ($trimmed === '==LESSON==') {
                if ($currentLesson && $currentModule) {
                    if ($currentLesson['content'] === '' && $contentBuffer) {
                        $currentLesson['content'] = $contentBuffer;
                    }
                    $currentModule['lessons'][] = $currentLesson;
                    $contentBuffer = '';
                }
                $currentLesson = ['name' => '', 'content' => '', 'video_url' => null, 'order' => $currentModule ? count($currentModule['lessons']) + 1 : 1];
                $state = 'lesson';
                $inContent = false;
                continue;
            }
            if ($trimmed === '==EXAM==') {
                if ($currentQuestion && $currentExam) {
                    if ($currentOption) {
                        $currentQuestion['options'][] = $currentOption;
                        $currentOption = null;
                    }
                    $currentExam['questions'][] = $currentQuestion;
                    $currentQuestion = null;
                }
                if ($currentExam) {
                    $data['exams'][] = $currentExam;
                }
                $currentExam = ['name' => '', 'description' => '', 'passing_score' => 6, 'max_attempts' => 3, 'questions' => []];
                $state = 'exam';
                $inContent = false;
                continue;
            }
            if ($trimmed === '==QUESTION==') {
                if ($currentQuestion && $currentExam) {
                    if ($currentOption) {
                        $currentQuestion['options'][] = $currentOption;
                        $currentOption = null;
                    }
                    $currentExam['questions'][] = $currentQuestion;
                }
                $currentQuestion = ['text' => '', 'type' => 'multiple_choice', 'points' => 1, 'options' => []];
                $state = 'question';
                $inContent = false;
                continue;
            }
            if ($trimmed === '==OPTION==') {
                if ($currentOption && $currentQuestion) {
                    $currentQuestion['options'][] = $currentOption;
                }
                $currentOption = ['text' => '', 'is_correct' => false];
                $state = 'option';
                $inContent = false;
                continue;
            }

            if ($inContent && $currentLesson) {
                $contentBuffer .= $line . "\n";
                continue;
            }

            if (!str_contains($trimmed, ':')) {
                if ($state === 'lesson' && $currentLesson && !empty($trimmed)) {
                    $inContent = true;
                    $contentBuffer = $trimmed . "\n";
                }
                continue;
            }

            $colonPos = strpos($trimmed, ':');
            $key = trim(substr($trimmed, 0, $colonPos));
            $value = trim(substr($trimmed, $colonPos + 1));

            switch ($state) {
                case 'course':
                    if ($key === 'name') $data['name'] = $value;
                    if ($key === 'description') $data['description'] = $value;
                    if ($key === 'level') $data['level'] = $value;
                    if ($key === 'slug') $data['slug'] = $value;
                    break;
                case 'module':
                    if ($key === 'name') $currentModule['name'] = $value;
                    if ($key === 'description') $currentModule['description'] = $value;
                    if ($key === 'order') $currentModule['order'] = (int)$value;
                    break;
                case 'lesson':
                    if ($key === 'name') $currentLesson['name'] = $value;
                    if ($key === 'video_url') $currentLesson['video_url'] = $value;
                    if ($key === 'order') $currentLesson['order'] = (int)$value;
                    if ($key === 'content') {
                        $currentLesson['content'] = $value;
                        $inContent = true;
                        $contentBuffer = '';
                    }
                    break;
                case 'exam':
                    if ($key === 'name') $currentExam['name'] = $value;
                    if ($key === 'description') $currentExam['description'] = $value;
                    if ($key === 'passing_score') $currentExam['passing_score'] = (float)$value;
                    if ($key === 'max_attempts') $currentExam['max_attempts'] = (int)$value;
                    break;
                case 'question':
                    if ($key === 'text') $currentQuestion['text'] = $value;
                    if ($key === 'type') $currentQuestion['type'] = $value;
                    if ($key === 'points') $currentQuestion['points'] = (float)$value;
                    break;
                case 'option':
                    if ($key === 'text') $currentOption['text'] = $value;
                    if ($key === 'is_correct') $currentOption['is_correct'] = in_array(strtolower($value), ['1', 'true', 'yes', 'si']);
                    break;
            }
        }

        // Flush remaining
        if ($currentOption && $currentQuestion) {
            $currentQuestion['options'][] = $currentOption;
        }
        if ($currentQuestion && $currentExam) {
            $currentExam['questions'][] = $currentQuestion;
        }
        if ($currentExam) {
            $data['exams'][] = $currentExam;
        }
        if ($currentLesson && $currentModule) {
            if ($currentLesson['content'] === '' && $contentBuffer) {
                $currentLesson['content'] = $contentBuffer;
            }
            $currentModule['lessons'][] = $currentLesson;
        }
        if ($currentModule) {
            $data['modules'][] = $currentModule;
        }

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']) . '-' . Str::random(5);
        }

        return $data;
    }

    protected function saveCourse($courseData)
    {
        DB::beginTransaction();

        try {
            $courseId = DB::table('academy_courses')->insertGetId([
                'name' => $courseData['name'],
                'slug' => $courseData['slug'] ?? Str::slug($courseData['name']) . '-' . Str::random(5),
                'description' => $courseData['description'] ?? '',
                'level' => $courseData['level'] ?? 'beginner',
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $moduleMap = [];

            foreach ($courseData['modules'] ?? [] as $moduleData) {
                $moduleId = DB::table('academy_modules')->insertGetId([
                    'course_id' => $courseId,
                    'name' => $moduleData['name'],
                    'description' => $moduleData['description'] ?? '',
                    'order' => $moduleData['order'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $moduleMap[$moduleData['name']] = $moduleId;

                foreach ($moduleData['lessons'] ?? [] as $lessonData) {
                    DB::table('academy_lessons')->insert([
                        'module_id' => $moduleId,
                        'name' => $lessonData['name'],
                        'content' => $lessonData['content'] ?? '',
                        'video_url' => $lessonData['video_url'] ?? null,
                        'order' => $lessonData['order'] ?? 0,
                        'is_published' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            foreach ($courseData['exams'] ?? [] as $examData) {
                $targetModuleId = null;
                if (!empty($examData['module_name']) && isset($moduleMap[$examData['module_name']])) {
                    $targetModuleId = $moduleMap[$examData['module_name']];
                }

                $examId = DB::table('academy_exams')->insertGetId([
                    'course_id' => $courseId,
                    'module_id' => $targetModuleId,
                    'name' => $examData['name'],
                    'description' => $examData['description'] ?? '',
                    'passing_score' => $examData['passing_score'] ?? 6,
                    'max_attempts' => $examData['max_attempts'] ?? 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($examData['questions'] ?? [] as $questionData) {
                    $questionId = DB::table('academy_questions')->insertGetId([
                        'exam_id' => $examId,
                        'question_text' => $questionData['text'],
                        'type' => $questionData['type'] ?? 'multiple_choice',
                        'points' => $questionData['points'] ?? 1,
                        'order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    foreach ($questionData['options'] ?? [] as $index => $optionData) {
                        DB::table('academy_question_options')->insert([
                            'question_id' => $questionId,
                            'option_text' => $optionData['text'],
                            'is_correct' => $optionData['is_correct'],
                            'order' => $index,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            $course = DB::table('academy_courses')->find($courseId);

            return response()->json([
                'message' => 'Curso importado correctamente',
                'course' => $course,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al guardar el curso: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function downloadTemplate()
    {
        $templatePath = storage_path('app/courses/plantilla-curso.txt');

        if (!file_exists($templatePath)) {
            $this->generateTemplateTxt();
        }

        return response()->download($templatePath, 'plantilla-curso.txt');
    }

    protected function generateTemplateTxt()
    {
        $content = '==COURSE==
name: Nombre del Curso Aqui
slug: nombre-del-curso
description: Breve descripcion del curso.
level: beginner

==MODULE==
name: Nombre del Modulo
description: Descripcion del modulo.
order: 1

==LESSON==
name: Nombre de la Leccion
order: 1
content: <p>Contenido de la leccion en HTML. Puedes usar &lt;strong&gt;negrita&lt;/strong&gt;, &lt;em&gt;cursiva&lt;/em&gt;, y cualquier etiqueta HTML.</p><p>Tambien puedes incluir &lt;img src="https://ejemplo.com/imagen.jpg" alt="descripcion"&gt; y &lt;a href="https://ejemplo.com"&gt;enlaces&lt;/a&gt;.</p>

==EXAM==
name: Examen de Ejemplo
description: Descripcion del examen.
passing_score: 6
max_attempts: 3

==QUESTION==
text: Texto de la pregunta?
type: multiple_choice
points: 1
==OPTION==
text: Opcion correcta
is_correct: 1
==OPTION==
text: Opcion incorrecta
is_correct: 0
==OPTION==
text: Otra opcion incorrecta
is_correct: 0
';
        file_put_contents(storage_path('app/courses/plantilla-curso.txt'), $content);
    }

    private function textRunToHtml($element, $imageStoragePath)
    {
        $html = '';
        foreach ($element->getElements() as $part) {
            if ($part instanceof \PhpOffice\PhpWord\Element\Link) {
                $text = $this->sanitizeUtf8($part->getText());
                $source = $this->sanitizeUtf8($part->getSource());
                $html .= '<a href="' . htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
            } elseif ($part instanceof \PhpOffice\PhpWord\Element\Text) {
                $text = $this->sanitizeUtf8($part->getText());
                $style = $part->getFontStyle();

                if ($style && $style->isBold()) {
                    $text = '<strong>' . $text . '</strong>';
                }
                if ($style && $style->isItalic()) {
                    $text = '<em>' . $text . '</em>';
                }

                $html .= $text;
            } elseif ($part instanceof \PhpOffice\PhpWord\Element\Image) {
                $imageName = 'img_' . uniqid() . '.' . $part->getImageExtension();
                $imagePath = storage_path('app/' . $imageStoragePath . '/' . $imageName);

                $dir = dirname($imagePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($imagePath, $part->getImageString());
                $html .= '<img src="' . asset('storage/academy/imported/' . $imageName) . '" alt="course image" />';
            }
        }
        return $html;
    }

    private function parseTable($table)
    {
        $html = '<table border="1" style="border-collapse: collapse; width: 100%;">';
        foreach ($table->getRows() as $row) {
            $html .= '<tr>';
            foreach ($row->getCells() as $cell) {
                $html .= '<td style="padding: 5px; border: 1px solid #ccc;">';
                foreach ($cell->getElements() as $cellElement) {
                    if ($cellElement instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        $html .= $this->textRunToHtml($cellElement, '');
                    }
                }
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }
}
