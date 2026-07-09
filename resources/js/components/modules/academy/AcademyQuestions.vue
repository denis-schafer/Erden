<template>
    <div class="academy-questions p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Preguntas: {{ examName }}</h2>
            <button class="btn btn-indigo" @click="showForm = true; editing = null; form = defaultForm()">
                <i class="bi bi-plus-lg"></i> Nueva Pregunta
            </button>
        </div>
        <div v-if="!questions.length" class="text-center py-5 text-muted">No hay preguntas. Crea la primera.</div>
        <div v-for="(q, i) in questions" :key="q.id" class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">Pregunta {{ i + 1 }} <small class="text-muted">({{ q.points }} pts)</small></span>
                <div>
                    <button class="btn btn-sm btn-outline-primary me-1" @click="editQuestion(q)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" @click="deleteQuestion(q)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="card-body">
                <p>{{ q.question_text }}</p>
                <div v-for="(opt, j) in q.options" :key="opt.id" class="d-flex align-items-center mb-1">
                    <span class="me-2">{{ String.fromCharCode(65 + j) }}.</span>
                    <span :class="opt.is_correct ? 'text-success fw-bold' : ''">{{ opt.option_text }} {{ opt.is_correct ? '✓' : '' }}</span>
                </div>
            </div>
        </div>
        <div v-if="showForm" class="modal-backdrop fade show" @click="showForm = false"></div>
        <div v-if="showForm" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">{{ editing ? 'Editar' : 'Nueva' }} Pregunta</h5><button type="button" class="btn-close" @click="showForm = false"></button></div>
                    <form @submit.prevent="saveQuestion">
                        <div class="modal-body">
                            <div class="mb-3"><label class="form-label">Texto de la pregunta</label><textarea v-model="form.question_text" class="form-control" rows="2" required></textarea></div>
                            <div class="mb-3"><label class="form-label">Puntos</label><input v-model.number="form.points" type="number" class="form-control" step="0.5"></div>
                            <div class="mb-3">
                                <label class="form-label">Opciones <small class="text-muted">(marcar la correcta)</small></label>
                                <div v-for="(opt, j) in form.options" :key="j" class="input-group mb-2">
                                    <span class="input-group-text">{{ String.fromCharCode(65 + j) }}</span>
                                    <input v-model="opt.option_text" class="form-control" :placeholder="'Opción ' + (j + 1)" required>
                                    <div class="input-group-text">
                                        <input type="radio" :value="j" v-model="correctIndex" class="form-check-input mt-0">
                                    </div>
                                    <button class="btn btn-outline-danger" type="button" @click="form.options.splice(j, 1); if (correctIndex === j) correctIndex = -1; if (correctIndex > j) correctIndex--" v-if="form.options.length > 2">&times;</button>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="form.options.push({ option_text: '', is_correct: false })">+ Agregar opción</button>
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
import { ref, onMounted, watch } from 'vue';
import api from '../../../services/api';
import { useTabs } from '../../../composables/useTabs';
const { getTabData } = useTabs();
const examId = ref(null); const examName = ref('');
const questions = ref([]); const showForm = ref(false); const editing = ref(null);
const correctIndex = ref(-1);
const form = ref({ exam_id: examId.value, question_text: '', points: 1, options: [{ option_text: '', is_correct: false }, { option_text: '', is_correct: false }] });
watch(correctIndex, (val) => { form.value.options.forEach((o, i) => o.is_correct = i === val); });
function defaultForm() { return { exam_id: examId.value, question_text: '', points: 1, options: [{ option_text: '', is_correct: false }, { option_text: '', is_correct: false }] }; }
async function loadQuestions() { try { const { data } = await api.get('/academy/questions/' + examId.value); questions.value = data; } catch (e) { console.error(e); } }
async function saveQuestion() {
    try {
        const payload = { ...form.value, exam_id: examId.value };
        if (editing.value) { await api.put('/academy/questions/' + editing.value.id, payload); } else { await api.post('/academy/questions', payload); }
        showForm.value = false; correctIndex.value = -1; await loadQuestions();
    } catch (e) { console.error(e); }
}
function editQuestion(q) {
    editing.value = q; correctIndex.value = q.options.findIndex(o => o.is_correct);
    form.value = { exam_id: examId.value, question_text: q.question_text, points: q.points, options: q.options.map(o => ({ option_text: o.option_text, is_correct: o.is_correct })) };
    showForm.value = true;
}
async function deleteQuestion(q) { if (!confirm('¿Eliminar pregunta?')) return; try { await api.delete('/academy/questions/' + q.id); await loadQuestions(); } catch (e) { console.error(e); } }
onMounted(() => { const td = getTabData(); if (td) { examId.value = td.examId; examName.value = td.examName || ''; } if (examId.value) loadQuestions(); });
</script>