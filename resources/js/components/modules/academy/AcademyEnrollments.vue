<template>
    <div class="academy-enrollments p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Inscripciones</h2>
            <button class="btn btn-indigo" @click="showEnrollForm = true; loadAvailableStudents()">
                <i class="bi bi-plus-lg"></i> Inscribir Alumnos
            </button>
        </div>
        <div class="mb-3">
            <select v-model="filterCourse" class="form-select" @change="loadEnrollments">
                <option value="">Todos los cursos</option>
                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Alumno</th><th>DNI</th><th>Curso</th><th>Estado</th><th>Inscrito</th><th>Acciones</th></tr></thead>
                <tbody>
                    <tr v-for="e in enrollments" :key="e.id">
                        <td>{{ e.first_name }} {{ e.last_name }}</td><td>{{ e.dni }}</td><td>{{ e.course_name }}</td>
                        <td><span class="badge" :class="e.status === 'active' ? 'bg-success' : 'bg-secondary'">{{ e.status === 'active' ? 'Activo' : 'Completado' }}</span></td>
                        <td>{{ e.enrolled_at ? new Date(e.enrolled_at).toLocaleDateString() : '-' }}</td>
                        <td><button class="btn btn-sm btn-outline-danger" @click="deleteEnrollment(e)"><i class="bi bi-trash"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="showEnrollForm" class="modal-backdrop fade show" @click="showEnrollForm = false"></div>
        <div v-if="showEnrollForm" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Inscribir Alumnos</h5><button type="button" class="btn-close" @click="showEnrollForm = false"></button></div>
                    <form @submit.prevent="saveEnrollments">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Curso</label>
                                <select v-model="enrollForm.course_id" class="form-select" required @change="loadAvailableStudents">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Alumnos disponibles</label>
                                <div v-if="!availableStudents.length" class="text-muted small">No hay alumnos disponibles</div>
                                <div v-for="s in availableStudents" :key="s.id" class="form-check">
                                    <input type="checkbox" :value="s.id" v-model="enrollForm.student_ids" class="form-check-input" :id="'s'+s.id">
                                    <label class="form-check-label" :for="'s'+s.id">{{ s.last_name }}, {{ s.first_name }} ({{ s.dni }})</label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showEnrollForm = false">Cancelar</button>
                            <button type="submit" class="btn btn-indigo" :disabled="!enrollForm.student_ids.length">Inscribir ({{ enrollForm.student_ids.length }})</button>
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
const enrollments = ref([]);
const courses = ref([]);
const filterCourse = ref('');
const showEnrollForm = ref(false);
const availableStudents = ref([]);
const enrollForm = ref({ course_id: '', student_ids: [] });
async function loadEnrollments() {
    try { const params = {}; if (filterCourse.value) params.course_id = filterCourse.value; const { data } = await api.get('/academy/enrollments', { params }); enrollments.value = data.data || data; } catch (e) { console.error(e); }
}
async function loadCourses() { try { const { data } = await api.get('/academy/courses'); courses.value = data.data || data; } catch (e) { console.error(e); } }
async function loadAvailableStudents() {
    if (!enrollForm.value.course_id) { availableStudents.value = []; return; }
    try { const { data } = await api.get('/academy/enrollments/available-students/' + enrollForm.value.course_id); availableStudents.value = data; } catch (e) { console.error(e); }
}
async function saveEnrollments() {
    try { await api.post('/academy/enrollments', enrollForm.value); showEnrollForm.value = false; enrollForm.value = { course_id: '', student_ids: [] }; await loadEnrollments(); } catch (e) { console.error(e); }
}
async function deleteEnrollment(e) { if (!confirm('¿Eliminar inscripción?')) return; try { await api.delete('/academy/enrollments/' + e.id); await loadEnrollments(); } catch (e) { console.error(e); } }
onMounted(() => { loadEnrollments(); loadCourses(); });
</script>