<template>
    <div class="portal-dashboard">
        <h4 class="text-white mb-4">Mis Cursos</h4>
        <div v-if="!courses.length" class="text-center py-5">
            <div class="spinner-border text-light" v-if="loading"></div>
            <p v-else class="text-white-50">No estás inscrito en ningún curso.</p>
        </div>
        <div class="row g-3">
            <div v-for="course in courses" :key="course.id" class="col-md-6 col-lg-4">
                <div class="card course-card h-100 border-0 shadow-sm" @click="$emit('viewCourse', course.id)">
                    <div v-if="course.cover_image" class="course-cover" :style="{ backgroundImage: 'url(' + course.cover_image + ')' }"></div>
                    <div class="card-body">
                        <h5 class="card-title">{{ course.name }}</h5>
                        <p class="card-text text-muted small">{{ course.description?.substring(0, 100) }}{{ course.description?.length > 100 ? '...' : '' }}</p>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Progreso</span>
                                <span>{{ course.progress }}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" :style="{ width: course.progress + '%' }"></div>
                            </div>
                        </div>
                        <small class="text-muted">{{ course.completed_lessons }}/{{ course.total_lessons }} lecciones</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({ token: { type: String, required: true } });
defineEmits(['viewCourse']);
const courses = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const { data } = await axios.get('/curso/courses', {
            headers: { Authorization: 'Bearer ' + props.token, 'X-Company-Db': localStorage.getItem('academy_company_db') }
        });
        courses.value = data;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
});
</script>
<style scoped>
.course-card { cursor: pointer; transition: transform 0.2s; border-radius: 12px; overflow: hidden; }
.course-card:hover { transform: translateY(-4px); }
.course-cover { height: 120px; background-size: cover; background-position: center; }
</style>