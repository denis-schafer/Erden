<template>
    <div class="portal-exam-result">
        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-light"></div></div>
        <template v-else-if="attempt">
            <div class="card border-0 shadow-sm mb-3 text-center">
                <div class="card-body py-4">
                    <div :class="attempt.passed ? 'text-success' : 'text-danger'" style="font-size: 4rem;">
                        <i :class="attempt.passed ? 'bi bi-trophy-fill' : 'bi bi-emoji-frown'"></i>
                    </div>
                    <h3 class="mt-2" :class="attempt.passed ? 'text-success' : 'text-danger'">
                        {{ attempt.passed ? '¡Aprobado!' : 'No alcanzaste el mínimo' }}
                    </h3>
                    <div class="display-6 fw-bold my-3">{{ attempt.score }} / {{ attempt.max_score }}</div>
                    <div class="progress mx-auto" style="max-width: 300px; height: 12px;">
                        <div class="progress-bar" :class="attempt.passed ? 'bg-success' : 'bg-danger'" :style="{ width: percentage + '%' }"></div>
                    </div>
                    <p class="text-muted mt-2">{{ percentage }}% - Mínimo: {{ attempt.exam?.passing_score || 6 }}</p>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3" v-for="(ans, i) in attempt.answers" :key="ans.id">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h6>{{ i + 1 }}. {{ ans.question_text }}</h6>
                        <span :class="ans.is_correct ? 'text-success' : 'text-danger'">
                            {{ ans.is_correct ? '+' + ans.points_earned : '0' }} pts
                        </span>
                    </div>
                    <div v-for="opt in ans.all_options" :key="opt.id" class="d-flex align-items-center mb-1">
                        <span :class="{
                            'text-success fw-bold': opt.is_correct,
                            'text-danger': opt.id === ans.selected_option_id && !ans.is_correct
                        }">
                            <i v-if="opt.is_correct" class="bi bi-check-circle-fill me-1"></i>
                            <i v-if="opt.id === ans.selected_option_id && !opt.is_correct" class="bi bi-x-circle-fill me-1"></i>
                            {{ opt.option_text }}
                        </span>
                    </div>
                </div>
            </div>
            <button class="btn btn-outline-light w-100" @click="$emit('back')">
                <i class="bi bi-arrow-left"></i> Volver al curso
            </button>
        </template>
        <div v-else class="text-center py-5 text-white-50">No se encontraron resultados.</div>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({ token: { type: String, required: true }, attemptId: { type: [Number, String], required: true } });
defineEmits(['back']);
const attempt = ref(null);
const loading = ref(true);
const percentage = computed(() => attempt.value?.max_score > 0 ? Math.round((attempt.value.score / attempt.value.max_score) * 100) : 0);

onMounted(async () => {
    try {
        const { data } = await axios.get('/curso/exam-results/' + props.attemptId, {
            headers: { Authorization: 'Bearer ' + props.token, 'X-Company-Db': localStorage.getItem('academy_company_db') }
        });
        attempt.value = data;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
});
</script>