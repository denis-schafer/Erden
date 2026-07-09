<template>
    <div class="portal-login">
        <div class="login-card">
            <div class="text-center mb-4">
                <h3 class="fw-bold" style="color: var(--portal-primary, #4F46E5);">Academy</h3>
                <p class="text-muted">Aprendizaje interactivo</p>
            </div>
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
            <template v-if="step === 'company'">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre de la institución</label>
                    <div class="input-group">
                        <input class="form-control" v-model="companyInput" placeholder="Ej: miColegio" @keyup.enter="searchCompany" :disabled="searching">
                        <button class="btn btn-primary" @click="searchCompany" :disabled="searching">
                            <span v-if="searching" class="spinner-border spinner-border-sm"></span>
                            <span v-else>Buscar</span>
                        </button>
                    </div>
                </div>
            </template>
            <template v-if="step === 'course'">
                <p class="text-muted small mb-2">{{ businessName }}</p>
                <div class="mb-3">
                    <label class="form-label fw-bold">Seleccioná tu curso</label>
                    <select v-model="selectedCourse" class="form-select" :disabled="loadingCourses">
                        <option value="">Cargando cursos...</option>
                        <option v-for="c in courses" :key="c.id" :value="c.slug">{{ c.name }}</option>
                    </select>
                </div>
                <button class="btn btn-primary w-100" @click="step = 'login'" :disabled="!selectedCourse">Continuar</button>
            </template>
            <template v-if="step === 'login'">
                <p class="text-muted small mb-2">{{ businessName }} — {{ selectedCourseName }}</p>
                <form @submit.prevent="login">
                    <div class="mb-3">
                        <label class="form-label fw-bold">DNI</label>
                        <input class="form-control" v-model="form.dni" required placeholder="Tu número de DNI" :disabled="authenticating" inputmode="numeric">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Contraseña</label>
                        <input type="password" class="form-control" v-model="form.password" required placeholder="Tu contraseña" :disabled="authenticating">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg" :disabled="authenticating">
                        <span v-if="authenticating" class="spinner-border spinner-border-sm me-1"></span>
                        Ingresar
                    </button>
                </form>
            </template>
            <template v-if="step === 'auto-login'">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                    <p>Ingresando automáticamente...</p>
                </div>
            </template>
        </div>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const emit = defineEmits(['login-success']);
const props = defineProps({
    initialCourseSlug: { type: String, default: '' },
    initialDni: { type: String, default: '' },
});

const step = ref('company');
const companyInput = ref('');
const businessName = ref('');
const selectedCompany = ref('');
const selectedCourse = ref('');
const courses = ref([]);
const loadingCourses = ref(false);
const searching = ref(false);
const authenticating = ref(false);
const error = ref('');
const form = ref({ dni: '', password: '' });

const selectedCourseName = computed(() => {
    const found = courses.value.find(c => c.slug === selectedCourse.value);
    return found?.name || selectedCourse.value;
});

const loadPortalConfig = async (companyDb) => {
    try {
        const { data } = await axios.get('/curso/portal-config', { params: { company_db: companyDb } });
        localStorage.setItem('academy_portal_config', JSON.stringify(data));
    } catch (e) { /* defaults */ }
};

const searchCompany = async () => {
    if (!companyInput.value.trim()) return;
    searching.value = true; error.value = '';
    try {
        const { data } = await axios.get('/curso/lookup-company', { params: { name: companyInput.value.trim() } });
        selectedCompany.value = data.db;
        businessName.value = data.name;
        await loadPortalConfig(data.db);
        await loadCourses(data.db);
        step.value = 'course';
    } catch (e) {
        error.value = e.response?.data?.error || 'Institución no encontrada';
    } finally { searching.value = false; }
};

const loadCourses = async (companyDb) => {
    loadingCourses.value = true;
    try {
        const { data } = await axios.get('/curso/available-courses', { params: { company_db: companyDb } });
        courses.value = data;
        if (props.initialCourseSlug) {
            const match = courses.value.find(c => c.slug === props.initialCourseSlug);
            if (match) {
                selectedCourse.value = match.slug;
            }
        }
        if (!selectedCourse.value && courses.value.length === 1) {
            selectedCourse.value = courses.value[0].slug;
        }
    } catch (e) {
        error.value = 'Error al cargar cursos';
    } finally { loadingCourses.value = false; }
};

const login = async () => {
    authenticating.value = true; error.value = '';
    try {
        const { data } = await axios.post('/curso/login', {
            dni: form.value.dni,
            password: form.value.password,
        }, {
            headers: { 'X-Company-Db': selectedCompany.value },
        });
        emit('login-success', { ...data, company_db: selectedCompany.value });
    } catch (e) {
        error.value = e.response?.data?.message || 'Error al iniciar sesión';
    } finally { authenticating.value = false; }
};

const tryAutoLogin = async () => {
    step.value = 'auto-login'; authenticating.value = true; error.value = '';
    try {
        const { data } = await axios.post('/curso/login', {
            dni: form.value.dni,
            password: form.value.dni,
        }, {
            headers: { 'X-Company-Db': selectedCompany.value },
        });
        emit('login-success', { ...data, company_db: selectedCompany.value });
    } catch (e) {
        step.value = 'login'; authenticating.value = false;
    }
};

const findCompanyByCourseSlug = async (slug) => {
    try {
        const companies = await axios.get('/curso/lookup-company', { params: { all: 1 } });
        return null;
    } catch (e) { return null; }
};

const resolveCompany = async (slug) => {
    if (!slug) return false;
    searching.value = true;
    try {
        const { data } = await axios.get('/curso/lookup-company', { params: { course_slug: slug } });
        if (data.db) {
            selectedCompany.value = data.db;
            businessName.value = data.name || 'Academy';
            await loadPortalConfig(data.db);
            await loadCourses(data.db);
            return true;
        }
    } catch (e) { /* not found */ }
    finally { searching.value = false; }
    return false;
};

onMounted(async () => {
    if (props.initialCourseSlug) {
        companyInput.value = props.initialCourseSlug;
        const resolved = await resolveCompany(props.initialCourseSlug);
        if (!resolved) {
            error.value = 'Curso no encontrado. Buscá tu institución.';
            step.value = 'company';
            return;
        }
        if (props.initialDni) {
            form.value.dni = props.initialDni;
            if (selectedCourse.value) {
                await tryAutoLogin();
            } else {
                step.value = 'course';
            }
        } else {
            if (selectedCourse.value) {
                step.value = 'login';
            } else {
                step.value = 'course';
            }
        }
    }
});
</script>
<style scoped>
.portal-login { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.login-card { background: white; border-radius: 16px; padding: 32px; width: 100%; max-width: 420px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
</style>