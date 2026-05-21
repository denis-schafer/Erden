<template>
    <div class="pos-login-container">
        <div class="smoke-bubble bubble-1"></div>
        <div class="smoke-bubble bubble-2"></div>

        <div class="login-card">
            <div class="login-header">
                <div class="login-logo" v-html="logoSvg"></div>
                <p class="login-company">{{ companyName }}</p>
            </div>
            <div class="login-body">
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
                    <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
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
import { ref, reactive, onMounted } from 'vue';
import api from '../../../services/api';

const emit = defineEmits(['login-success']);

const props = defineProps({
    companyDb: {
        type: String,
        required: true
    },
    companyName: {
        type: String,
        default: 'POS'
    }
});

const form = reactive({
    username: '',
    password: ''
});

const loading = ref(false);
const error = ref('');
const logoSvg = ref('');

const handleLogin = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await api.post('/pos/login', form);

        localStorage.setItem('pos_token', response.data.token);
        localStorage.setItem('pos_user', JSON.stringify(response.data.user));

        emit('login-success', response.data);
    } catch (err) {
        error.value = err.response?.data?.message || 'Credenciales incorrectas';
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    const token = localStorage.getItem('pos_token');
    if (token) {
        emit('login-success', { user: JSON.parse(localStorage.getItem('pos_user')), token });
    }
    try {
        const resp = await fetch('/img/logo.svg');
        logoSvg.value = await resp.text();
    } catch (e) {
        console.warn('Failed to load logo SVG:', e);
    }
});
</script>

<style scoped>
.pos-login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #0a1520 0%, #152d42 50%, #1a4a6e 100%);
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
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(35,145,193,0.1) 0%, transparent 70%);
    top: -100px;
    right: -80px;
    animation: float1 25s ease-in-out infinite;
}

.bubble-2 {
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%);
    bottom: -60px;
    left: -50px;
    animation: float2 30s ease-in-out infinite;
}

@keyframes float1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-30px, 20px) scale(1.1); }
}

@keyframes float2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(40px, -30px) scale(0.9); }
}

.login-card {
    width: 100%;
    max-width: 360px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    animation: cardEnter 0.5s ease-out;
    position: relative;
    z-index: 1;
}

@keyframes cardEnter {
    from { opacity: 0; transform: translateY(30px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.login-header {
    background: linear-gradient(135deg, #0a1628 0%, #1a3a5c 50%, #1a4a6e 100%);
    padding: 2rem 1.5rem 1.5rem;
    text-align: center;
}

.login-logo {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 35px;
}

.login-logo svg {
    display: block;
    width: 100%;
    max-width: 140px;
    height: auto;
    margin: 0;
}

.login-company {
    color: rgba(255,255,255,0.6);
    font-size: 0.82rem;
    margin: 0.75rem 0 0;
    text-align: center;
}

.login-body {
    background: #fff;
    padding: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.35rem;
    display: block;
}

.input-icon-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon-wrapper .bi {
    position: absolute;
    left: 12px;
    color: #adb5bd;
    font-size: 0.9rem;
    z-index: 2;
    transition: color 0.2s;
}

.input-icon-wrapper .form-control {
    padding-left: 38px;
    height: 42px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.9rem;
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

.btn-login {
    width: 100%;
    height: 44px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #1a4a6e, #2391c1);
    color: #fff;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
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
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
    transition: left 0.5s;
}

.btn-login:hover::before {
    left: 100%;
}

.btn-login:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(35,145,193,0.3);
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
    font-size: 1rem;
}

.alert {
    border-radius: 8px;
    font-size: 0.85rem;
    padding: 0.6rem 0.9rem;
}
</style>
