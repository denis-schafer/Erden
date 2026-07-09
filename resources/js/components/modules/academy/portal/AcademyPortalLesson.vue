<template>
    <div class="portal-lesson">
        <button class="btn btn-sm btn-outline-light mb-3" @click="$emit('back')">
            <i class="bi bi-arrow-left"></i> Volver al curso
        </button>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-light"></div></div>
        <template v-else-if="lesson">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h4 class="card-title">{{ lesson.name }}</h4>
                    <small class="text-muted">{{ lesson.module_name }}</small>
                </div>
            </div>
            <div v-if="lesson.video_url" class="mb-3">
                <div class="video-container">
                    <iframe :src="lesson.video_url" frameborder="0" allowfullscreen></iframe>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body lesson-content" v-html="lesson.content || '<p class=\'text-muted\'>Contenido próximamente.</p>'">
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <button v-if="lesson.prev_lesson" class="btn btn-outline-light" @click="loadLesson(lesson.prev_lesson.id)">
                    <i class="bi bi-chevron-left"></i> Anterior
                </button>
                <div class="flex-grow-1"></div>
                <button v-if="!lesson.completed" class="btn btn-success btn-lg px-4" @click="markComplete" :disabled="completing">
                    <span v-if="completing" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-check-lg me-1"></i>
                    Marcar como completada
                </button>
                <div v-else class="text-success fw-bold">
                    <i class="bi bi-check-circle-fill me-1"></i> Completada
                </div>
                <div class="flex-grow-1"></div>
                <button v-if="lesson.next_lesson" class="btn btn-outline-light" @click="loadLesson(lesson.next_lesson.id)">
                    Siguiente <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </template>
    </div>
</template>
<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({ token: { type: String, required: true }, lessonId: { type: [Number, String], required: true } });
const emit = defineEmits(['back', 'completed']);
const lesson = ref(null);
const loading = ref(true);
const completing = ref(false);

const headers = () => ({ Authorization: 'Bearer ' + props.token, 'X-Company-Db': localStorage.getItem('academy_company_db') });

const loadLesson = async (id) => {
    loading.value = true;
    try { const { data } = await axios.get('/curso/lessons/' + id, { headers: headers() }); lesson.value = data.lesson; lesson.value.prev_lesson = data.prev_lesson; lesson.value.next_lesson = data.next_lesson; } catch (e) { console.error(e); } finally { loading.value = false; }
};

const markComplete = async () => {
    completing.value = true;
    try { await axios.post('/curso/lessons/' + lesson.value.id + '/complete', {}, { headers: headers() }); lesson.value.completed = true; } catch (e) { console.error(e); } finally { completing.value = false; }
};

onMounted(() => loadLesson(props.lessonId));
watch(() => props.lessonId, (val) => { if (val) loadLesson(val); });
</script>
<style scoped>
.video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 12px; }
.video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
.lesson-content { font-size: 1.05rem; line-height: 1.7; }
.lesson-content :deep(img) { max-width: 100%; height: auto; border-radius: 8px; margin: 1rem 0; }
.lesson-content :deep(pre) { background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 8px; overflow-x: auto; }
.lesson-content :deep(code) { background: #f4f4f4; padding: 0.15rem 0.3rem; border-radius: 3px; font-size: 0.9em; }
.lesson-content :deep(pre code) { background: transparent; padding: 0; color: inherit; }
</style>