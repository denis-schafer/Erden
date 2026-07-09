<template>
    <div class="portal-course">
        <button class="btn btn-sm btn-outline-light mb-3" @click="$emit('back')">
            <i class="bi bi-arrow-left"></i> Volver
        </button>
        <h4 class="text-white mb-3" v-if="course">{{ course.name }}</h4>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-light"></div></div>
        <div v-else-if="data" v-for="mod in data.modules" :key="mod.id" class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span>{{ mod.name }}</span>
                <span class="small text-muted">{{ mod.progress }}%</span>
            </div>
            <div class="card-body p-0">
                <div v-for="lesson in mod.lessons" :key="lesson.id" class="lesson-item d-flex justify-content-between align-items-center px-3 py-2 border-bottom" :class="{ 'bg-light': lesson.completed }">
                    <div>
                        <span :class="lesson.completed ? 'text-decoration-line-through text-muted' : ''">
                            <i v-if="lesson.completed" class="bi bi-check-circle-fill text-success me-2"></i>
                            <i v-else class="bi bi-circle text-muted me-2"></i>
                            {{ lesson.name }}
                        </span>
                    </div>
                    <div>
                        <button v-if="!lesson.completed" class="btn btn-sm btn-outline-primary" @click="$emit('viewLesson', lesson.id)">Ir</button>
                        <span v-else class="badge bg-success">Completado</span>
                    </div>
                </div>
            </div>
            <div v-if="mod.exams?.length" class="card-footer bg-white">
                <div class="fw-bold small text-muted mb-1">Ex&aacute;menes del m&oacute;dulo</div>
                <div v-for="exam in mod.exams" :key="exam.id" class="d-flex justify-content-between align-items-center py-1">
                    <span><i class="bi bi-question-circle text-warning me-2"></i>{{ exam.name }}</span>
                    <div>
                        <span v-if="exam.passed" class="badge bg-success me-2">Aprobado ({{ exam.best_score }})</span>
                        <button class="btn btn-sm btn-outline-warning" @click="$emit('viewExam', exam.id)">
                            {{ exam.passed ? 'Reintentar' : 'Rendir' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="data.course_exams?.length" class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Ex&aacute;menes del curso</div>
            <div class="card-body p-0">
                <div v-for="exam in data.course_exams" :key="exam.id" class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span><i class="bi bi-question-circle text-warning me-2"></i>{{ exam.name }}</span>
                    <div>
                        <span v-if="exam.passed" class="badge bg-success me-2">Aprobado ({{ exam.best_score }})</span>
                        <button class="btn btn-sm btn-outline-warning" @click="$emit('viewExam', exam.id)">
                            {{ exam.passed ? 'Reintentar' : 'Rendir' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({ token: { type: String, required: true }, courseId: { type: [Number, String], required: true } });
defineEmits(['viewLesson', 'viewExam', 'back']);
const data = ref(null);
const course = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        const headers = { Authorization: 'Bearer ' + props.token, 'X-Company-Db': localStorage.getItem('academy_company_db') };
        const { data: res } = await axios.get('/curso/modules/' + props.courseId, { headers });
        data.value = res;
        course.value = res.course;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
});
</script>
<style scoped>
.lesson-item { cursor: pointer; }
.lesson-item:hover:not(.bg-light) { background: #f8f9fa; }
</style>