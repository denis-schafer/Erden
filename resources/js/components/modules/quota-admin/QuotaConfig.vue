<template>
    <div class="quota-config p-3">
        <h4 class="mb-4">Configuración</h4>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <div class="card mb-4">
                <div class="card-header">Configuración General</div>
                <div class="card-body">
                    <div v-for="cfg in visibleConfigs" :key="cfg.id" class="row mb-3 align-items-center">
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

            <div class="card mb-4">
                <div class="card-header">Usuario por Defecto (Portal MP)</div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <strong>Cashier para pagos de socios</strong>
                            <p class="text-muted small mb-0">Las cuotas pagadas vía portal se asignarán a este usuario.</p>
                        </div>
                        <div class="col-md-5">
                            <select class="form-select" v-model="defaultCashierId">
                                <option value="">Seleccionar cashier...</option>
                                <option v-for="u in cashiers" :key="u.id" :value="String(u.id)">{{ u.name }} ({{ u.username }})</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-primary" @click="saveDefaultCashier">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Mensaje WhatsApp</div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        Variables disponibles:
                        <code>%name%</code> (nombre),
                        <code>%last_name%</code> (apellido),
                        <code>%month%</code> (mes actual),
                        <code>%year%</code> (año actual),
                        <code>%amount%</code> (deuda total)
                    </p>
                    <div class="row align-items-start">
                        <div class="col-md-10 mb-2">
                            <textarea class="form-control form-control-sm" rows="3" v-model="whatsappTemplate"></textarea>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-primary" @click="saveWhatsappTemplate">Guardar</button>
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
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';

const configs = ref([]);
const loading = ref(true);
const cashiers = ref([]);
const defaultCashierId = ref('');
const whatsappTemplate = ref('');

const visibleConfigs = computed(() => configs.value.filter(c => c.name !== 'default_cashier_id' && c.name !== 'whatsapp_message_template'));

const getLabel = (name) => {
    const labels = {
        business_name: 'Nombre del Natatorio',
        redirect_uri: 'Redirect URI MP',
        mp_access_token: 'Access Token MP',
        default_cashier_id: 'Cashier por defecto (portal)',
    };
    return labels[name] || name;
};

const loadConfigs = async () => {
    try {
        const { data } = await axios.get('/quota/config');
        configs.value = data;
        const cfg = data.find(c => c.name === 'default_cashier_id');
        if (cfg) defaultCashierId.value = cfg.value || '';
        const tpl = data.find(c => c.name === 'whatsapp_message_template');
        if (tpl) whatsappTemplate.value = tpl.value || '';
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const loadCashiers = async () => {
    try {
        const { data } = await axios.get('/quota/config/cashiers');
        cashiers.value = data;
    } catch (e) { console.error(e); }
};

const saveConfig = async (cfg) => {
    try {
        await axios.put(`/quota/config/${cfg.id}`, { value: cfg.value });
        toast.success('Guardado');
    } catch (e) {
        toast.error('Error al guardar');
    }
};

const saveDefaultCashier = async () => {
    try {
        let cfg = configs.value.find(c => c.name === 'default_cashier_id');
        if (cfg) {
            await axios.put(`/quota/config/${cfg.id}`, { value: defaultCashierId.value });
        } else {
            await axios.post('/quota/config', { name: 'default_cashier_id', value: defaultCashierId.value, type: 'string' });
            loadConfigs();
        }
        toast.success('Cashier por defecto guardado');
    } catch (e) {
        toast.error('Error al guardar');
    }
};

const saveWhatsappTemplate = async () => {
    try {
        let cfg = configs.value.find(c => c.name === 'whatsapp_message_template');
        if (cfg) {
            await axios.put(`/quota/config/${cfg.id}`, { value: whatsappTemplate.value });
        } else {
            await axios.post('/quota/config', { name: 'whatsapp_message_template', value: whatsappTemplate.value, type: 'text' });
            loadConfigs();
        }
        toast.success('Plantilla WhatsApp guardada');
    } catch (e) {
        toast.error('Error al guardar');
    }
};

onMounted(() => {
    loadConfigs();
    loadCashiers();
});
</script>

<style scoped>
.card-header { background: #fff; border-bottom: 1px solid #e9ecef; font-weight: 600; }
</style>
