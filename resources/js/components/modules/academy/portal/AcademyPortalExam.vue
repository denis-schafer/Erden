<template>
    <div class="portal-exam">
        <button class="btn btn-sm btn-outline-light mb-3" @click="confirmExit">
            <i class="bi bi-arrow-left"></i> Salir del examen
        </button>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-light"></div></div>
        <div v-else-if="maxReached" class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <h4 class="text-warning">Límite de intentos alcanzado</h4>
                <p v-if="bestAttempt">Mejor puntaje: {{ bestAttempt.score }} / {{ bestAttempt.max_score }}</p>
                <button class="btn btn-outline-light mt-3" @click="$emit('back')">Volver al curso</button>
            </div>
        </div>
        <template v-else-if="exam">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ exam.name }}</h5>
                        <span class="badge bg-info">Intento {{ exam.attempt_number }}</span>
                    </div>
                    <p class="text-muted small mt-2">{{ exam.description }}</p>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>{{ currentIndex + 1 }} / {{ exam.questions.length }} preguntas</span>
                        <span>{{ answeredCount }} respondidas</span>
                    </div>
                </div>
            </div>
            <div v-if="exam.questions.length" class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">{{ currentIndex + 1 }}. {{ currentQuestion.question_text }}</h5>
                    <div v-for="(opt, j) in currentQuestion.options" :key="opt.id" class="form-check mb-2">
                        <input type="radio" :value="opt.id" v-model="answers[currentQuestion.id]" class="form-check-input" :id="'opt' + opt.id" :name="'q' + currentQuestion.id">
                        <label class="form-check-label" :for="'opt' + opt.id" style="font-size: 1.05rem;">
                            {{ String.fromCharCode(65 + j) }}. {{ opt.option_text }}
                        </label>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between">
                    <button class="btn btn-outline-secondary" @click="prevQuestion" :disabled="currentIndex === 0">
                        <i class="bi bi-chevron-left"></i> Anterior
                    </button>
                    <button v-if="currentIndex < exam.questions.length - 1" class="btn btn-primary" @click="nextQuestion">
                        Siguiente <i class="bi bi-chevron-right"></i>
                    </button>
                    <button v-else class="btn btn-success btn-lg" @click="submitExam" :disabled="submitting">
                        <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                        Finalizar examen
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({ token: { type: String, required: true }, examId: { type: [Number, String], required: true } });
const emit = defineEmits(['back', 'finished']);
const exam = ref(null);
const loading = ref(true);
const submitting = ref(false);
const maxReached = ref(false);
const bestAttempt = ref(null);
const currentIndex = ref(0);
const answers = ref({});

const currentQuestion = computed(() => exam.value?.questions[currentIndex.value] || {});
const answeredCount = computed(() => Object.keys(answers.value).length);

const headers = () => ({ Authorization: 'Bearer ' + props.token, 'X-Company-Db': localStorage.getItem('academy_company_db') });

const loadExam = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/curso/exams/' + props.examId, { headers: headers() });
        if (data.max_attempts_reached) {
            maxReached.value = true;
            bestAttempt.value = data.best_attempt;
        } else {
            exam.value = data.exam;
            answers.value = {};
            currentIndex.value = 0;
        }
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const nextQuestion = () => { if (currentIndex.value < exam.value.questions.length - 1) currentIndex.value++; };
const prevQuestion = () => { if (currentIndex.value > 0) currentIndex.value--; };

const submitExam = async () => {
    if (!confirm('¿Finalizar examen? Las respuestas no enviadas se perderán.')) return;
    submitting.value = true;
    try {
        const payload = { answers: Object.entries(answers.value).map(([qid, optId]) => ({ question_id: parseInt(qid), selected_option_id: optId })) };
        const { data } = await axios.post('/curso/exams/' + props.examId + '/submit', payload, { headers: headers() });
        emit('finished', { attempt_id: data.attempt_id });
    } catch (e) { console.error(e); }
    finally { submitting.value = false; }
};

const confirmExit = () => { if (Object.keys(answers.value).length > 0 && !confirm('Si sales ahora, perderás tus respuestas. ¿Continuar?')) return; emit('back'); };

onMounted(loadExam);
</script>