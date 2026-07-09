<template>
    <div class="academy-courses p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Cursos</h2>
            <button class="btn btn-indigo" @click="showForm = true; editing = null; form = defaultForm()">
                <i class="bi bi-plus-lg"></i> Nuevo Curso
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Nombre</th><th>Nivel</th><th>Módulos</th><th>Alumnos</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <tr v-for="course in courses" :key="course.id">
                        <td>{{ course.name }}</td>
                        <td><span class="badge" :class="levelBadge(course.level)">{{ course.level }}</span></td>
                        <td>{{ course.modules_count }}</td>
                        <td>{{ course.students_count }}</td>
                        <td><span class="badge" :class="course.is_published ? 'bg-success' : 'bg-secondary'">{{ course.is_published ? 'Publicado' : 'Borrador' }}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" @click="editCourse(course)" title="Editar"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-info me-1" @click="viewCourse(course)" title="Módulos"><i class="bi bi-collection"></i></button>
                            <button class="btn btn-sm btn-outline-danger" @click="deleteCourse(course)" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="paginator" class="d-flex justify-content-center mt-3">
            <nav><ul class="pagination pagination-sm">
                <li class="page-item" :class="{ disabled: !paginator.prev_page_url }"><button class="page-link" @click="loadPage(paginator.prev_page_url)">Anterior</button></li>
                <li class="page-item" :class="{ disabled: !paginator.next_page_url }"><button class="page-link" @click="loadPage(paginator.next_page_url)">Siguiente</button></li>
            </ul></nav>
        </div>
        <div v-if="showForm" class="modal-backdrop fade show" @click="showForm = false"></div>
        <div v-if="showForm" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editing ? 'Editar' : 'Nuevo' }} Curso</h5>
                        <button type="button" class="btn-close" @click="showForm = false"></button>
                    </div>
                    <form @submit.prevent="saveCourse">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input v-model="form.name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea v-model="form.description" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nivel</label>
                                <select v-model="form.level" class="form-select">
                                    <option value="beginner">Principiante</option>
                                    <option value="intermediate">Intermedio</option>
                                    <option value="advanced">Avanzado</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL de portada</label>
                                <input v-model="form.cover_image" class="form-control" placeholder="https://...">
                            </div>
                            <div class="form-check">
                                <input v-model="form.is_published" type="checkbox" class="form-check-input" id="pub">
                                <label class="form-check-label" for="pub">Publicado</label>
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
const courses = ref([]);
const paginator = ref(null);
const showForm = ref(false);
const editing = ref(null);
const form = ref(defaultForm());
function defaultForm() { return { name: '', description: '', level: 'beginner', cover_image: '', is_published: false }; }
const levelBadge = (l) => ({ beginner: 'bg-success', intermediate: 'bg-warning text-dark', advanced: 'bg-danger' }[l] || 'bg-secondary');
async function loadCourses() {
    try { const { data } = await api.get('/academy/courses'); courses.value = data.data || data; paginator.value = data; } catch (e) { console.error(e); }
}
async function loadPage(url) { if (!url) return; try { const { data } = await api.get(url.replace(/.*academy/, '/academy')); courses.value = data.data || data; paginator.value = data; } catch (e) { console.error(e); } }
async function saveCourse() {
    try {
        if (editing.value) { await api.put('/academy/courses/' + editing.value.id, form.value); } else { await api.post('/academy/courses', form.value); }
        showForm.value = false; await loadCourses();
    } catch (e) { console.error(e); }
}
function editCourse(c) { editing.value = c; form.value = { name: c.name, description: c.description, level: c.level, cover_image: c.cover_image, is_published: c.is_published }; showForm.value = true; }
function viewCourse(c) { window.openTab({ route: 'academy-modules', name: 'Módulos: ' + c.name, data: { courseId: c.id, courseName: c.name } }); }
async function deleteCourse(c) { if (!confirm('¿Eliminar curso ' + c.name + '?')) return; try { await api.delete('/academy/courses/' + c.id); await loadCourses(); } catch (e) { console.error(e); } }
onMounted(loadCourses);
</script>