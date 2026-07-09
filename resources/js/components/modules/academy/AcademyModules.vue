<template>
    <div class="academy-modules p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Módulos: {{ courseName }}</h2>
            <button class="btn btn-indigo" @click="showForm = true; editing = null; form = defaultForm()">
                <i class="bi bi-plus-lg"></i> Nuevo Módulo
            </button>
        </div>
        <div v-if="!modules.length" class="text-center py-5 text-muted">No hay módulos. Crea el primero.</div>
        <div v-for="(mod, i) in modules" :key="mod.id" class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">{{ i + 1 }}. {{ mod.name }}</span>
                <div>
                    <button class="btn btn-sm btn-outline-primary me-1" @click="editModule(mod)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-info me-1" @click="viewLessons(mod)"><i class="bi bi-list-ul"></i> Lecciones</button>
                    <button class="btn btn-sm btn-outline-danger" @click="deleteModule(mod)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div v-if="mod.description" class="card-body text-muted small">{{ mod.description }}</div>
            <div class="card-footer bg-white text-muted small">{{ mod.lessons_count }} lecciones</div>
        </div>
        <div v-if="showForm" class="modal-backdrop fade show" @click="showForm = false"></div>
        <div v-if="showForm" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">{{ editing ? 'Editar' : 'Nuevo' }} Módulo</h5><button type="button" class="btn-close" @click="showForm = false"></button></div>
                    <form @submit.prevent="saveModule">
                        <div class="modal-body">
                            <div class="mb-3"><label class="form-label">Nombre</label><input v-model="form.name" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Descripción</label><textarea v-model="form.description" class="form-control" rows="2"></textarea></div>
                            <div class="mb-3"><label class="form-label">Orden</label><input v-model.number="form.order" type="number" class="form-control"></div>
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
import { useTabs } from '../../../composables/useTabs';
const { getTabData } = useTabs();
const courseId = ref(null);
const courseName = ref('');
const modules = ref([]);
const showForm = ref(false);
const editing = ref(null);
const form = ref(defaultForm());
function defaultForm() { return { course_id: courseId.value, name: '', description: '', order: 0 }; }
async function loadModules() {
    try { const { data } = await api.get('/academy/modules/' + courseId.value); modules.value = data; } catch (e) { console.error(e); }
}
async function saveModule() {
    try {
        form.value.course_id = courseId.value;
        if (editing.value) { await api.put('/academy/modules/' + editing.value.id, form.value); } else { await api.post('/academy/modules', form.value); }
        showForm.value = false; await loadModules();
    } catch (e) { console.error(e); }
}
function editModule(m) { editing.value = m; form.value = { course_id: courseId.value, name: m.name, description: m.description, order: m.order }; showForm.value = true; }
function viewLessons(m) { window.openTab({ route: 'academy-lessons', name: 'Lecciones: ' + m.name, data: { moduleId: m.id, moduleName: m.name, courseName: courseName.value } }); }
async function deleteModule(m) { if (!confirm('¿Eliminar módulo ' + m.name + '?')) return; try { await api.delete('/academy/modules/' + m.id); await loadModules(); } catch (e) { console.error(e); } }
onMounted(() => {
    const tabData = getTabData();
    if (tabData) { courseId.value = tabData.courseId; courseName.value = tabData.courseName || ''; }
    if (courseId.value) loadModules();
});
</script>