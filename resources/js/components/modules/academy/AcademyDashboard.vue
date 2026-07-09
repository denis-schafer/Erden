<template>
    <div class="academy-dashboard p-3">
        <h2 class="mb-4">Dashboard Academy</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-book fs-1 text-indigo"></i>
                        <h3 class="mt-2">{{ stats.courses_count || 0 }}</h3>
                        <p class="text-muted mb-0">Cursos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-people fs-1 text-success"></i>
                        <h3 class="mt-2">{{ stats.students_count || 0 }}</h3>
                        <p class="text-muted mb-0">Alumnos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-pencil-square fs-1 text-warning"></i>
                        <h3 class="mt-2">{{ stats.enrollments_count || 0 }}</h3>
                        <p class="text-muted mb-0">Inscripciones</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-question-circle fs-1 text-danger"></i>
                        <h3 class="mt-2">{{ stats.exams_count || 0 }}</h3>
                        <p class="text-muted mb-0">Exámenes</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold">Cursos recientes</div>
                    <div class="card-body">
                        <div v-if="stats.recent_courses?.length" v-for="course in stats.recent_courses" :key="course.id" class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>{{ course.name }}</span>
                            <span class="badge" :class="course.is_published ? 'bg-success' : 'bg-secondary'">{{ course.is_published ? 'Publicado' : 'Borrador' }}</span>
                        </div>
                        <p v-else class="text-muted mb-0">No hay cursos recientes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold">Alumnos recientes</div>
                    <div class="card-body">
                        <div v-if="stats.recent_students?.length" v-for="student in stats.recent_students" :key="student.id" class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>{{ student.first_name }} {{ student.last_name }}</span>
                            <span class="text-muted small">{{ student.dni }}</span>
                        </div>
                        <p v-else class="text-muted mb-0">No hay alumnos recientes</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../services/api';
const stats = ref({});
onMounted(async () => {
    try {
        const { data } = await api.get('/academy/dashboard');
        stats.value = data;
    } catch (e) { console.error(e); }
});
</script>
<style scoped>
.academy-dashboard { min-height: 100%; background-color: #f8f9fa; background-image: var(--bg-image, none); background-position: center; background-size: cover; background-repeat: no-repeat; background-attachment: fixed; }
</style>