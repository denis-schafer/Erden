<template>
    <div class="login-container">
        <div class="smoke-bubble bubble-1"></div>
        <div class="smoke-bubble bubble-2"></div>
        <div class="smoke-bubble bubble-3"></div>
        <div class="smoke-bubble bubble-4"></div>

        <svg class="waves" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path class="wave-1" fill="rgba(255,255,255,0.04)" d="M0,224L48,213.3C96,203,192,181,288,181.3C384,181,480,203,576,218.7C672,235,768,245,864,229.3C960,213,1056,171,1152,154.7C1248,139,1344,149,1392,154.7L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            <path class="wave-2" fill="rgba(255,255,255,0.03)" d="M0,192L48,181.3C96,171,192,149,288,138.7C384,128,480,128,576,138.7C672,149,768,171,864,165.3C960,160,1056,128,1152,117.3C1248,107,1344,117,1392,122.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>

        <div class="login-card">
            <div class="login-header">
                <div class="login-logo" v-html="logoSvg"></div>
            </div>
            <div class="login-body">
                <p class="login-subtitle">Sistema de Gestión</p>
                <form @submit.prevent="handleLogin">
                    <div class="form-group">
                        <label class="form-label">Usuario</label>
                        <div class="input-icon-wrapper">
                            <i class="bi bi-person"></i>
                            <input
                                v-model="form.username"
                                type="text"
                                class="form-control"
                                required
                                autocomplete="username"
                                placeholder="Ingrese su usuario"
                                @blur="checkUserType"
                            >
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <div class="input-icon-wrapper">
                            <i class="bi bi-lock"></i>
                            <input
                                v-model="form.password"
                                type="password"
                                class="form-control"
                                required
                                autocomplete="current-password"
                                placeholder="Ingrese su contraseña"
                            >
                        </div>
                    </div>
                    <div v-if="showCompanyField" class="form-group">
                        <label class="form-label">Empresa</label>
                        <div class="input-icon-wrapper">
                            <i class="bi bi-building"></i>
                            <input
                                v-model="form.company_db"
                                type="text"
                                class="form-control"
                                placeholder="Nombre o código de empresa"
                                required
                            >
                        </div>
                        <div class="form-text">Ingrese el nombre o código de la empresa</div>
                    </div>
                    <div v-if="error" class="alert alert-danger">{{ error }}</div>
                    <button type="submit" class="btn-login" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                        <span v-else class="btn-icon">&#10132;</span>
                        Iniciar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { toast as toastify } from '../../utils/toast';
import api from '../../services/api';

const authStore = useAuthStore();

const logoSvg = ref('');

const form = reactive({
    username: '',
    password: '',
    company_db: ''
});

const loading = ref(false);
const error = ref('');
const isGlobalUser = ref(false);
const userChecked = ref(true);

onMounted(async () => {
    try {
        const response = await fetch('/img/logo.svg');
        const text = await response.text();
        logoSvg.value = text;
    } catch (e) {
        console.warn('Failed to load logo SVG:', e);
    }
});

const showCompanyField = computed(() => {
    return !isGlobalUser.value;
});

const emit = defineEmits(['login-success']);

const checkUserType = async () => {
    if (!form.username) {
        isGlobalUser.value = false;
        form.company_db = '';
        return;
    }

    try {
        const response = await api.get('/check-user', {
            params: { username: form.username }
        });
        isGlobalUser.value = response.data.is_global;

        if (isGlobalUser.value) {
            form.company_db = '';
        }
    } catch (err) {
        isGlobalUser.value = false;
    }
};

const handleLogin = async () => {
    loading.value = true;
    error.value = '';

    try {
        const data = await authStore.login(form);

        if (data.needs_company_selection) {
            emit('show-company-selector', data.companies);
        } else {
            authStore.setAuth(data);
            emit('login-success');
            toastify.success('Sesión iniciada correctamente');
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Credenciales incorrectas';
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #0d2137 0%, #1a6d91 50%, #2391c1 100%);
    background-size: 400% 400%;
    animation: gradientShift 20s ease infinite;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    25% { background-position: 100% 0%; }
    50% { background-position: 100% 100%; }
    75% { background-position: 0% 100%; }
    100% { background-position: 0% 50%; }
}

.smoke-bubble {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: 0;
}

.bubble-1 {
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(77,184,232,0.15) 0%, transparent 70%);
    top: -150px;
    right: -100px;
    animation: float1 25s ease-in-out infinite;
}

.bubble-2 {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(35,145,193,0.12) 0%, transparent 70%);
    bottom: -100px;
    left: -80px;
    animation: float2 30s ease-in-out infinite;
}

.bubble-3 {
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
    top: 40%;
    left: 60%;
    animation: float3 20s ease-in-out infinite;
}

.bubble-4 {
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(77,184,232,0.08) 0%, transparent 70%);
    top: 20%;
    left: 10%;
    animation: float4 28s ease-in-out infinite;
}

@keyframes float1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(60px, -40px) scale(1.1); }
    66% { transform: translate(-30px, 30px) scale(0.9); }
}

@keyframes float2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-50px, -30px) scale(1.15); }
    66% { transform: translate(40px, 20px) scale(0.85); }
}

@keyframes float3 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-40px, 50px) scale(1.2); }
}

@keyframes float4 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(50px, -60px) scale(0.9); }
}

.waves {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 200px;
    z-index: 0;
    pointer-events: none;
}

.wave-1 {
    animation: waveAnim 8s ease-in-out infinite alternate;
}

.wave-2 {
    animation: waveAnim 12s ease-in-out infinite alternate-reverse;
}

@keyframes waveAnim {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50px); }
}

.login-card {
    width: 100%;
    max-width: 420px;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0,0,0,0.35);
    animation: cardEnter 0.6s ease-out;
    position: relative;
    z-index: 1;
}

@keyframes cardEnter {
    from {
        opacity: 0;
        transform: translateY(40px) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.login-header {
    background: linear-gradient(135deg, #0a1628 0%, #1a4a6e 50%, #1a6d91 100%);
    padding: 2.5rem 2rem 2rem;
    text-align: center;
    position: relative;
}

.login-logo {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 50px;
}

.login-logo svg {
    display: block;
    width: 100%;
    max-width: 180px;
    height: auto;
    margin: 0;
}

.login-body {
    background: #fff;
    padding: 2rem;
}

.login-subtitle {
    color: #6c757d;
    font-size: 0.95rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.4rem;
    display: block;
}

.input-icon-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon-wrapper .bi {
    position: absolute;
    left: 14px;
    color: #adb5bd;
    font-size: 1rem;
    z-index: 2;
    transition: color 0.2s;
}

.input-icon-wrapper .form-control {
    padding-left: 42px;
    height: 46px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.25s;
    background: #f8f9fa;
}

.input-icon-wrapper .form-control:focus {
    border-color: #2391c1;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(35,145,193,0.1);
}

.input-icon-wrapper:focus-within .bi {
    color: #2391c1;
}

.form-text {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 0.35rem;
}

.btn-login {
    width: 100%;
    height: 48px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, #1a6d91, #2391c1);
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
}

.btn-login::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    transition: left 0.5s;
}

.btn-login:hover::before {
    left: 100%;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(35,145,193,0.35);
}

.btn-login:active {
    transform: translateY(0);
}

.btn-login:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-icon {
    font-size: 1.1rem;
}

.alert {
    border-radius: 10px;
    font-size: 0.9rem;
    padding: 0.75rem 1rem;
}
</style>
