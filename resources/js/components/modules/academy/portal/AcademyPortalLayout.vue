<template>
    <div class="portal-layout" :style="layoutStyle">
        <AcademyPortalLogin
            v-if="!isAuthenticated"
            :initial-course-slug="courseSlug"
            :initial-dni="dni"
            @login-success="handleLoginSuccess"
        />
        <div v-else class="portal-container">
            <header class="portal-header">
                <div class="container-fluid d-flex justify-content-between align-items-center py-2">
                    <h5 class="mb-0 text-white">{{ portalConfig.title || 'Academy' }}</h5>
                    <button class="btn btn-sm btn-outline-light" @click="handleLogout">Salir</button>
                </div>
            </header>
            <div class="container-fluid py-3">
                <AcademyPortalDashboard
                    v-if="currentView === 'dashboard'"
                    :token="token"
                    @view-course="viewCourse"
                />
                <AcademyPortalCourse
                    v-else-if="currentView === 'course'"
                    :token="token"
                    :course-id="selectedCourseId"
                    @view-lesson="viewLesson"
                    @view-exam="viewExam"
                    @back="currentView = 'dashboard'"
                />
                <AcademyPortalLesson
                    v-else-if="currentView === 'lesson'"
                    :token="token"
                    :lesson-id="selectedLessonId"
                    @back="currentView = 'course'"
                    @completed="onLessonCompleted"
                />
                <AcademyPortalExam
                    v-else-if="currentView === 'exam'"
                    :token="token"
                    :exam-id="selectedExamId"
                    @back="currentView = 'course'"
                    @finished="onExamFinished"
                />
                <AcademyPortalExamResult
                    v-else-if="currentView === 'exam-result'"
                    :token="token"
                    :attempt-id="selectedAttemptId"
                    @back="currentView = 'course'"
                />
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import AcademyPortalLogin from './AcademyPortalLogin.vue';
import AcademyPortalDashboard from './AcademyPortalDashboard.vue';
import AcademyPortalCourse from './AcademyPortalCourse.vue';
import AcademyPortalLesson from './AcademyPortalLesson.vue';
import AcademyPortalExam from './AcademyPortalExam.vue';
import AcademyPortalExamResult from './AcademyPortalExamResult.vue';

const props = defineProps({
    courseSlug: { type: String, default: '' },
    dni: { type: String, default: '' },
});

const token = ref(null);
const user = ref(null);
const companyDb = ref(null);
const portalConfig = ref({});
const currentView = ref('dashboard');
const selectedCourseId = ref(null);
const selectedLessonId = ref(null);
const selectedExamId = ref(null);
const selectedAttemptId = ref(null);

const isAuthenticated = computed(() => token.value && user.value);

const layoutStyle = computed(() => {
    const primary = portalConfig.value.primary_color || '#4F46E5';
    const secondary = portalConfig.value.secondary_color || '#7C3AED';
    return {
        '--portal-primary': primary,
        '--portal-secondary': secondary,
        background: `linear-gradient(135deg, ${primary} 0%, ${secondary} 100%)`,
    };
});

const handleLoginSuccess = (data) => {
    token.value = data.token;
    user.value = data.user;
    companyDb.value = data.company_db;
    localStorage.setItem('academy_token', data.token);
    localStorage.setItem('academy_user', JSON.stringify(data.user));
    localStorage.setItem('academy_company_db', data.company_db);
};

const handleLogout = () => {
    token.value = null;
    user.value = null;
    companyDb.value = null;
    currentView.value = 'dashboard';
    localStorage.removeItem('academy_token');
    localStorage.removeItem('academy_user');
    localStorage.removeItem('academy_company_db');
};

const viewCourse = (courseId) => {
    selectedCourseId.value = courseId;
    currentView.value = 'course';
};

const viewLesson = (lessonId) => {
    selectedLessonId.value = lessonId;
    currentView.value = 'lesson';
};

const viewExam = (examId) => {
    selectedExamId.value = examId;
    currentView.value = 'exam';
};

const onLessonCompleted = () => {
    currentView.value = 'course';
};

const onExamFinished = (data) => {
    selectedAttemptId.value = data.attempt_id;
    currentView.value = 'exam-result';
};

onMounted(async () => {
    const savedToken = localStorage.getItem('academy_token');
    const savedUser = localStorage.getItem('academy_user');
    const savedCompanyDb = localStorage.getItem('academy_company_db');
    if (savedToken && savedUser && savedCompanyDb) {
        token.value = savedToken;
        user.value = JSON.parse(savedUser);
        companyDb.value = savedCompanyDb;
    }
});
</script>
<style scoped>
.portal-layout { height: 100vh; height: 100dvh; display: flex; flex-direction: column; overflow: hidden; }
.portal-container { flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.portal-header { background: rgba(0,0,0,0.2); backdrop-filter: blur(10px); flex-shrink: 0; }
.portal-container > .container-fluid { flex: 1; overflow-y: auto; min-height: 0; height: 100%; }
</style>