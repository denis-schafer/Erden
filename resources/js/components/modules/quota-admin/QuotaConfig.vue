<template>
    <div class="quota-config p-3">
        <h4 class="mb-4">Configuración</h4>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <div class="card mb-4">
                <div class="card-header">Configuración General</div>
                <div class="card-body">
                    <div v-for="cfg in configs" :key="cfg.id" class="row mb-3 align-items-center">
                        <div class="col-md-4">
                            <strong>{{ getLabel(cfg.name) }}</strong>
                        </div>
                        <div class="col-md-6">
                            <input class="form-control form-control-sm" v-model="cfg.value">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-primary" @click="saveConfig(cfg)">
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">MercadoPago</div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        Para obtener el token de MercadoPago usá la página de OAuth.
                    </p>
                    <a class="btn btn-primary btn-sm" href="/oauth" target="_blank">
                        <i class="bi bi-box-arrow-up-right"></i> Ir a OAuth
                    </a>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';

const configs = ref([]);
const loading = ref(true);

const getLabel = (name) => {
    const labels = {
        business_name: 'Nombre del Natatorio',
        redirect_uri: 'Redirect URI MP',
        mp_access_token: 'Access Token MP',
    };
    return labels[name] || name;
};

const loadConfigs = async () => {
    try {
        const { data } = await axios.get('/quota/config');
        configs.value = data;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const saveConfig = async (cfg) => {
    try {
        await axios.put(`/quota/config/${cfg.id}`, { value: cfg.value });
        toast.success('Guardado');
    } catch (e) {
        toast.error('Error al guardar');
    }
};

onMounted(loadConfigs);
</script>

<style scoped>
.card-header { background: #fff; border-bottom: 1px solid #e9ecef; font-weight: 600; }
</style>
