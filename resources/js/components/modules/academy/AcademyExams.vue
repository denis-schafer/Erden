<template>
    <div class="academy-exams p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Exámenes</h2>
            <button class="btn btn-indigo" @click="showForm = true; editing = null; form = defaultForm(); loadCourses()">
                <i class="bi bi-plus-lg"></i> Nuevo Examen
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Nombre</th><th>Curso</th><th>Puntaje Mínimo</th><th>Intentos Max</th><th>Preguntas</th><th>Intentos</th><th>Acciones</th></tr></thead>
                <tbody>
                    <tr v-for="e in exams" :key="e.id">
                        <td>{{ e.name }}</td><td>{{ e.course_name }}</td><td>{{ e.passing_score }}</td><td>{{ e.max_attempts }}</td>
                        <td>{{ e.questions_count }}</td><td>{{ e.attempts_count }}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" @click="editExam(e)"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-info me-1" @click="viewQuestions(e)"><i class="bi bi-question-lg"></i></button>
                            <button class="btn btn-sm btn-outline-danger" @click="deleteExam(e)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="showForm" class="modal-backdrop fade show" @click="showForm = false"></div>
        <div v-if="showForm" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">{{ editing ? 'Editar' : 'Nuevo' }} Examen</h5><button type="button" class="btn-close" @click="showForm = false"></button></div>
                    <form @submit.prevent="saveExam">
                        <div class="modal-body">
                            <div class="mb-3"><label class="form-label">Curso</label><select v-model="form.course_id" class="form-select" required><option value="">Seleccionar...</option><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                            <div class="mb-3"><label class="form-label">Nombre</label><input v-model="form.name" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Descripción</label><textarea v-model="form.description" class="form-control" rows="2"></textarea></div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">Puntaje mínimo</label><input v-model.number="form.passing_score" type="number" class="form-control" step="0.5"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Intentos máximos</label><input v-model.number="form.max_attempts" type="number" class="form-control" min="1"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showForm = false">Cancelar</button>
                            <button type="submit" class="btn btn-indigo">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../services/api';
const exams = ref([]); const courses = ref([]);
const showForm = ref(false); const editing = ref(null);
const form = ref({ course_id: '', name: '', description: '', passing_score: 6, max_attempts: 3 });
async function loadExams() { try { const { data } = await api.get('/academy/exams'); exams.value = data.data || data; } catch (e) { console.error(e); } }
async function loadCourses() { try { const { data } = await api.get('/academy/courses'); courses.value = data.data || []; } catch (e) { console.error(e); } }
async function saveExam() { try { if (editing.value) { await api.put('/academy/exams/' + editing.value.id, form.value); } else { await api.post('/academy/exams', form.value); } showForm.value = false; await loadExams(); } catch (e) { console.error(e); } }
function editExam(e) { editing.value = e; form.value = { course_id: e.course_id, name: e.name, description: e.description, passing_score: e.passing_score, max_attempts: e.max_attempts }; showForm.value = true; loadCourses(); }
function viewQuestions(e) { window.openTab({ route: 'academy-questions', name: 'Preguntas: ' + e.name, data: { examId: e.id, examName: e.name } }); }
async function deleteExam(e) { if (!confirm('¿Eliminar examen ' + e.name + '?')) return; try { await api.delete('/academy/exams/' + e.id); await loadExams(); } catch (e) { console.error(e); } }
onMounted(loadExams);
</script>