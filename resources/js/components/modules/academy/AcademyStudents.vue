<template>
    <div class="academy-students p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Alumnos</h2>
            <button class="btn btn-indigo" @click="showForm = true; editing = null; form = defaultForm()">
                <i class="bi bi-plus-lg"></i> Nuevo Alumno
            </button>
        </div>
        <div class="mb-3">
            <input v-model="search" class="form-control" placeholder="Buscar por nombre, apellido o DNI..." @input="loadStudents">
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>DNI</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Cursos</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <tr v-for="s in students" :key="s.id">
                        <td>{{ s.dni }}</td><td>{{ s.first_name }} {{ s.last_name }}</td><td>{{ s.email || '-' }}</td><td>{{ s.phone || '-' }}</td>
                        <td>{{ s.courses_count }}</td>
                        <td><span class="badge" :class="s.is_active ? 'bg-success' : 'bg-secondary'">{{ s.is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" @click="editStudent(s)"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-warning me-1" @click="resetPassword(s)" title="Restablecer contraseña"><i class="bi bi-key"></i></button>
                            <button class="btn btn-sm btn-outline-danger" @click="deleteStudent(s)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="showForm" class="modal-backdrop fade show" @click="showForm = false"></div>
        <div v-if="showForm" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">{{ editing ? 'Editar' : 'Nuevo' }} Alumno</h5><button type="button" class="btn-close" @click="showForm = false"></button></div>
                    <form @submit.prevent="saveStudent">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">Nombre</label><input v-model="form.first_name" class="form-control" required></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Apellido</label><input v-model="form.last_name" class="form-control" required></div>
                            </div>
                            <div class="mb-3"><label class="form-label">DNI</label><input v-model="form.dni" class="form-control" required :disabled="!!editing"></div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">Email</label><input v-model="form.email" type="email" class="form-control"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Teléfono</label><input v-model="form.phone" class="form-control"></div>
                            </div>
                            <div class="form-check"><input v-model="form.is_active" type="checkbox" class="form-check-input" id="act"><label class="form-check-label" for="act">Activo</label></div>
                            <small class="text-muted">La contraseña inicial es el número de DNI.</small>
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
const students = ref([]);
const search = ref('');
const showForm = ref(false);
const editing = ref(null);
const form = ref(defaultForm());
function defaultForm() { return { first_name: '', last_name: '', dni: '', email: '', phone: '', is_active: true }; }
async function loadStudents() {
    try { const params = {}; if (search.value) params.search = search.value; const { data } = await api.get('/academy/students', { params }); students.value = data.data || data; } catch (e) { console.error(e); }
}
async function saveStudent() {
    try {
        if (editing.value) { await api.put('/academy/students/' + editing.value.id, form.value); } else { await api.post('/academy/students', form.value); }
        showForm.value = false; await loadStudents();
    } catch (e) { console.error(e); }
}
function editStudent(s) { editing.value = s; form.value = { first_name: s.first_name, last_name: s.last_name, dni: s.dni, email: s.email, phone: s.phone, is_active: s.is_active }; showForm.value = true; }
async function resetPassword(s) { if (!confirm('¿Restablecer contraseña de ' + s.first_name + ' ' + s.last_name + ' a su DNI?')) return; try { await api.post('/academy/students/' + s.id + '/reset-password'); alert('Contraseña restablecida'); } catch (e) { console.error(e); } }
async function deleteStudent(s) { if (!confirm('¿Eliminar alumno ' + s.first_name + ' ' + s.last_name + '?')) return; try { await api.delete('/academy/students/' + s.id); await loadStudents(); } catch (e) { console.error(e); } }
onMounted(loadStudents);
</script>