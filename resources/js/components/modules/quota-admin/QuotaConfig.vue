<template>
    <div class="quota-config p-3">
        <h4 class="mb-4">Configuración</h4>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <!-- General -->
            <div class="card mb-4"><div class="card-header">Configuración General</div><div class="card-body">
                <div v-for="cfg in visibleConfigs" :key="cfg.id" class="row mb-3 align-items-center">
                    <div class="col-md-4"><strong>{{ getLabel(cfg.name) }}</strong></div>
                    <div class="col-md-6"><input class="form-control form-control-sm" v-model="cfg.value"></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary" @click="saveConfig(cfg)">Guardar</button></div>
                </div>
            </div></div>

            <!-- Portal -->
            <div class="card mb-4"><div class="card-header">Personalización del Portal</div><div class="card-body">
                <div class="row mb-3 align-items-center"><div class="col-md-4"><strong>Logo</strong></div><div class="col-md-6">
                    <input class="form-control form-control-sm" type="file" accept="image/*" @change="uploadImage('portal_logo', $event)">
                    <div v-if="portalLogo" class="mt-2 d-flex align-items-center gap-2"><img :src="portalLogo" style="max-height:60px;">
                        <button class="btn btn-sm btn-outline-danger" @click="deleteImage('portal_logo')"><i class="bi bi-trash"></i></button></div>
                </div></div>
                <div class="row mb-3 align-items-center"><div class="col-md-4"><strong>Fondo</strong></div><div class="col-md-6">
                    <input class="form-control form-control-sm" type="file" accept="image/*" @change="uploadImage('portal_bg', $event)">
                    <div v-if="portalBg" class="mt-2 d-flex align-items-center gap-2"><img :src="portalBg" style="max-height:60px;">
                        <button class="btn btn-sm btn-outline-danger" @click="deleteImage('portal_bg')"><i class="bi bi-trash"></i></button></div>
                </div></div>
                <div class="row mb-3 align-items-center"><div class="col-md-4"><strong>Color primario</strong></div>
                    <div class="col-md-6"><input type="color" class="form-control form-control-color" v-model="portalPrimaryColor" style="width:60px;height:38px;"></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary" @click="saveColor('portal_primary_color', portalPrimaryColor)">Guardar</button></div>
                </div>
                <div class="row mb-3 align-items-center"><div class="col-md-4"><strong>Color secundario</strong></div>
                    <div class="col-md-6"><input type="color" class="form-control form-control-color" v-model="portalSecondaryColor" style="width:60px;height:38px;"></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary" @click="saveColor('portal_secondary_color', portalSecondaryColor)">Guardar</button></div>
                </div>
            </div></div>

            <!-- Sidebar theme -->
            <div class="card mb-4"><div class="card-header">Personalización del Sistema</div><div class="card-body">
                <div class="row mb-3 align-items-center"><div class="col-md-4"><strong>Color del menú (sidebar)</strong></div>
                    <div class="col-md-6"><input type="color" class="form-control form-control-color" v-model="sidebarPrimary" style="width:60px;height:38px;"></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary" @click="saveColor('primary_color', sidebarPrimary)">Guardar</button></div>
                </div>
                <div class="row mb-3 align-items-center"><div class="col-md-4"><strong>Color de acento</strong></div>
                    <div class="col-md-6"><input type="color" class="form-control form-control-color" v-model="sidebarAccent" style="width:60px;height:38px;"></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary" @click="saveColor('secondary_color', sidebarAccent)">Guardar</button></div>
                </div>
                <div class="row mb-3 align-items-center"><div class="col-md-4"><strong>Logo del sistema</strong></div><div class="col-md-6">
                    <input class="form-control form-control-sm" type="file" accept="image/*" @change="uploadImage('logo', $event)">
                    <div v-if="sidebarLogo" class="mt-2 d-flex align-items-center gap-2"><img :src="sidebarLogo" style="max-height:40px;">
                        <button class="btn btn-sm btn-outline-danger" @click="deleteImage('logo')"><i class="bi bi-trash"></i></button></div>
                </div></div>
                <div class="row mb-3 align-items-center"><div class="col-md-4"><strong>Fondo del Dashboard</strong></div><div class="col-md-6">
                    <input class="form-control form-control-sm" type="file" accept="image/*" @change="uploadImage('background_image', $event)">
                    <div v-if="sidebarBg" class="mt-2 d-flex align-items-center gap-2"><img :src="sidebarBg" style="max-height:40px;">
                        <button class="btn btn-sm btn-outline-danger" @click="deleteImage('background_image')"><i class="bi bi-trash"></i></button></div>
                </div></div>
                <div class="row mb-3 align-items-center"><div class="col-md-4"><strong>Arrastrar módulos del menú</strong></div>
                    <div class="col-md-6"><div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" :checked="sidebarDragDrop === '1'" @change="toggleSidebarDrag" id="sidebarDragSwitch">
                        <label class="form-check-label" for="sidebarDragSwitch">{{ sidebarDragDrop === '1' ? 'Activado' : 'Desactivado' }}</label>
                    </div></div>
                </div>
            </div></div>

            <!-- Default cashier -->
            <div class="card mb-4"><div class="card-header">Usuario por Defecto (Portal MP)</div><div class="card-body">
                <div class="row align-items-center"><div class="col-md-5"><strong>Cashier para pagos de socios</strong><p class="text-muted small mb-0">Las cuotas pagadas vía portal se asignarán a este usuario.</p></div>
                    <div class="col-md-5"><select class="form-select" v-model="defaultCashierId"><option value="">Seleccionar cashier...</option><option v-for="u in cashiers" :key="u.id" :value="String(u.id)">{{ u.name }} ({{ u.username }})</option></select></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary" @click="saveDefaultCashier">Guardar</button></div>
                </div>
            </div></div>

            <!-- WhatsApp -->
            <div class="card mb-4"><div class="card-header">Mensaje WhatsApp</div><div class="card-body">
                <p class="text-muted small mb-2">Variables: <code>%name%</code> <code>%last_name%</code> <code>%month%</code> <code>%year%</code> <code>%amount%</code> <code>%portal%</code></p>
                <div class="row align-items-start"><div class="col-md-10 mb-2"><textarea class="form-control form-control-sm" rows="3" v-model="whatsappTemplate"></textarea></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary" @click="saveWhatsappTemplate">Guardar</button></div>
                </div>
            </div></div>

            <!-- MP -->
            <div class="card mb-4"><div class="card-header">MercadoPago</div><div class="card-body">
                <p class="text-muted small mb-2">Para obtener el token de MercadoPago usá la página de OAuth.</p>
                <a class="btn btn-primary btn-sm" href="/oauth" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Ir a OAuth</a>
            </div></div>
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
const portalLogo = ref('');
const portalBg = ref('');
const portalPrimaryColor = ref('#667eea');
const portalSecondaryColor = ref('#764ba2');
const sidebarPrimary = ref('#212529');
const sidebarAccent = ref('#6c757d');
const sidebarLogo = ref('');
const sidebarBg = ref('');
const sidebarDragDrop = ref('0');

const visibleConfigs = computed(() => configs.value.filter(c =>
    !['default_cashier_id','whatsapp_message_template','portal_logo','portal_bg','portal_primary_color','portal_secondary_color',
      'primary_color','secondary_color','logo','background_image','sidebar_drag_drop'].includes(c.name)
));

const getLabel = (name) => ({ business_name:'Nombre',redirect_uri:'Redirect URI MP',mp_access_token:'Access Token MP' }[name] || name);

const getVal = (data, name) => { const c = data.find(x => x.name === name); return c ? c.value || '' : ''; };

const loadConfigs = async () => {
    try {
        const { data } = await axios.get('/quota/config');
        configs.value = data;
        defaultCashierId.value = getVal(data, 'default_cashier_id');
        whatsappTemplate.value = getVal(data, 'whatsapp_message_template');
        portalLogo.value = getVal(data, 'portal_logo');
        portalBg.value = getVal(data, 'portal_bg');
        portalPrimaryColor.value = getVal(data, 'portal_primary_color') || '#667eea';
        portalSecondaryColor.value = getVal(data, 'portal_secondary_color') || '#764ba2';
        sidebarPrimary.value = getVal(data, 'primary_color') || '#212529';
        sidebarAccent.value = getVal(data, 'secondary_color') || '#6c757d';
        sidebarLogo.value = getVal(data, 'logo');
        sidebarBg.value = getVal(data, 'background_image');
        sidebarDragDrop.value = getVal(data, 'sidebar_drag_drop') || '0';
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const loadCashiers = async () => {
    try { const { data } = await axios.get('/quota/config/cashiers'); cashiers.value = data; } catch (e) { console.error(e); }
};

const findId = (name) => { const c = configs.value.find(x => x.name === name); return c ? c.id : null; };

const saveConfig = async (cfg) => {
    try { await axios.put(`/quota/config/${cfg.id}`, { value: cfg.value }); toast.success('Guardado'); } catch (e) { toast.error('Error'); }
};

const saveDefaultCashier = async () => {
    try {
        const id = findId('default_cashier_id');
        if (id) { await axios.put(`/quota/config/${id}`, { value: defaultCashierId.value }); }
        else { await axios.post('/quota/config', { name:'default_cashier_id', value:defaultCashierId.value, type:'string' }); loadConfigs(); }
        toast.success('Guardado');
    } catch (e) { toast.error('Error'); }
};

const saveWhatsappTemplate = async () => {
    try {
        const id = findId('whatsapp_message_template');
        if (id) { await axios.put(`/quota/config/${id}`, { value: whatsappTemplate.value }); }
        else { await axios.post('/quota/config', { name:'whatsapp_message_template', value:whatsappTemplate.value, type:'text' }); loadConfigs(); }
        toast.success('Guardado');
    } catch (e) { toast.error('Error'); }
};

const uploadImage = async (name, event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('name', name);
    formData.append('file', file);
    try {
        const { data } = await axios.post('/quota/config/upload', formData);
        if (name === 'portal_logo') portalLogo.value = data.url;
        else if (name === 'portal_bg') portalBg.value = data.url;
        else if (name === 'logo') sidebarLogo.value = data.url;
        else if (name === 'background_image') sidebarBg.value = data.url;
        toast.success('Imagen subida');
    } catch (e) { toast.error('Error al subir imagen'); }
};

const deleteImage = async (name) => {
    const id = findId(name);
    if (!id) return;
    try {
        await axios.post(`/quota/config/${id}/delete-image`);
        if (name === 'portal_logo') portalLogo.value = '';
        else if (name === 'portal_bg') portalBg.value = '';
        else if (name === 'logo') sidebarLogo.value = '';
        else if (name === 'background_image') sidebarBg.value = '';
        toast.success('Imagen eliminada');
    } catch (e) { toast.error('Error al eliminar'); }
};

const saveColor = async (name, value) => {
    try {
        const id = findId(name);
        if (id) { await axios.put(`/quota/config/${id}`, { value }); }
        else { await axios.post('/quota/config', { name, value, type:'string' }); loadConfigs(); }
        toast.success('Color guardado');
    } catch (e) { toast.error('Error'); }
};

const toggleSidebarDrag = async () => {
    const newVal = sidebarDragDrop.value === '1' ? '0' : '1';
    sidebarDragDrop.value = newVal;
    const id = findId('sidebar_drag_drop');
    if (id) { await axios.put(`/quota/config/${id}`, { value: newVal }); }
    else { await axios.post('/quota/config', { name:'sidebar_drag_drop', value:newVal, type:'boolean' }); loadConfigs(); }
};

onMounted(() => { loadConfigs(); loadCashiers(); });
</script>

<style scoped>
.card-header { background: #fff; border-bottom: 1px solid #e9ecef; font-weight: 600; }
</style>
