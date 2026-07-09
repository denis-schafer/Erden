<template>
    <div class="academy-lessons p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Lecciones: {{ moduleName }} <small class="text-muted">({{ courseName }})</small></h2>
            <button class="btn btn-indigo" @click="showForm = true; editing = null; form = defaultForm()">
                <i class="bi bi-plus-lg"></i> Nueva Lecci&oacute;n
            </button>
        </div>
        <div v-if="!lessons.length" class="text-center py-5 text-muted">No hay lecciones. Crea la primera.</div>
        <div v-for="(lesson, i) in lessons" :key="lesson.id" class="card mb-2 border-0 shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center py-2">
                <div>
                    <span class="fw-bold">{{ i + 1 }}. {{ lesson.name }}</span>
                    <span v-if="lesson.video_url" class="ms-2 text-danger"><i class="bi bi-play-circle"></i></span>
                    <span class="ms-2 badge" :class="lesson.is_published ? 'bg-success' : 'bg-secondary'">{{ lesson.is_published ? 'Publicado' : 'Borrador' }}</span>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-primary me-1" @click="editLesson(lesson)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" @click="deleteLesson(lesson)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>
        <div v-if="showForm" class="modal-backdrop fade show" @click="closeForm"></div>
        <div v-if="showForm" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">{{ editing ? 'Editar' : 'Nueva' }} Lecci&oacute;n</h5><button type="button" class="btn-close" @click="closeForm"></button></div>
                    <form @submit.prevent="saveLesson">
                        <div class="modal-body">
                            <div class="mb-3"><label class="form-label">Nombre</label><input v-model="form.name" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Video URL (YouTube)</label><input v-model="form.video_url" class="form-control" placeholder="https://www.youtube.com/embed/..."></div>
                            <div class="mb-3">
                                <label class="form-label">Contenido <small class="text-muted">(editor visual)</small></label>
                                <div ref="quillContainer" class="quill-editor" style="height:300px;margin-bottom:10px;"></div>
                                <textarea v-model="form.content" class="form-control" rows="8" style="font-family:monospace;font-size:13px;" placeholder="HTML del contenido..."></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">Orden</label><input v-model.number="form.order" type="number" class="form-control"></div>
                                <div class="col-md-6 mb-3 pt-4">
                                    <div class="form-check">
                                        <input v-model="form.is_published" type="checkbox" class="form-check-input" id="pub">
                                        <label class="form-check-label" for="pub">Publicado</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeForm">Cancelar</button>
                            <button type="submit" class="btn btn-indigo">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted, watch, nextTick, onBeforeUnmount } from 'vue';
import api from '../../../services/api';
import { useTabs } from '../../../composables/useTabs';
const { getTabData } = useTabs();

const moduleId = ref(null);
const moduleName = ref('');
const courseName = ref('');
const lessons = ref([]);
const showForm = ref(false);
const editing = ref(null);
const form = ref(defaultForm());
const quillContainer = ref(null);
let quill = null;

function defaultForm() { return { module_id: moduleId.value, name: '', content: '', video_url: '', order: 0, is_published: true }; }

async function loadLessons() {
    try { const { data } = await api.get('/academy/lessons/' + moduleId.value); lessons.value = data; } catch (e) { console.error(e); }
}

function initEditor() {
    destroyEditor();
    nextTick(() => {
        if (!quillContainer.value || typeof Quill === 'undefined') return;
        quill = new Quill(quillContainer.value, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [1,2,3,false] }],
                    ['bold','italic','underline','strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link','image'],
                    ['clean'],
                    ['code-block']
                ]
            }
        });
        if (form.value.content) {
            quill.root.innerHTML = form.value.content;
        }
        quill.on('text-change', () => {
            form.value.content = quill.root.innerHTML;
        });
    });
}

function destroyEditor() {
    if (quill) {
        quill = null;
    }
}

watch(showForm, (val) => {
    if (val) setTimeout(initEditor, 100);
    else destroyEditor();
});

function closeForm() {
    showForm.value = false;
    form.value = defaultForm();
    editing.value = null;
}

async function saveLesson() {
    if (quill) form.value.content = quill.root.innerHTML;
    try {
        form.value.module_id = moduleId.value;
        if (editing.value) { await api.put('/academy/lessons/' + editing.value.id, form.value); } else { await api.post('/academy/lessons', form.value); }
        closeForm(); await loadLessons();
    } catch (e) { console.error(e); }
}

function editLesson(l) {
    editing.value = l;
    form.value = { module_id: moduleId.value, name: l.name, content: l.content, video_url: l.video_url, order: l.order, is_published: l.is_published };
    showForm.value = true;
}

async function deleteLesson(l) { if (!confirm('Eliminar leccion ' + l.name + '?')) return; try { await api.delete('/academy/lessons/' + l.id); await loadLessons(); } catch (e) { console.error(e); } }

onBeforeUnmount(() => destroyEditor());

onMounted(() => {
    const tabData = getTabData();
    if (tabData) { moduleId.value = tabData.moduleId; moduleName.value = tabData.moduleName || ''; courseName.value = tabData.courseName || ''; }
    if (moduleId.value) loadLessons();
});
</script>