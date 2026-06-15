<template>
    <div class="modal fade show d-block" tabindex="-1"
         style="background: rgba(0,0,0,0.5); z-index: 1060;"
         @click.self="$emit('close')">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ partner ? 'Editar Socio' : 'Nuevo Socio' }}</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">DNI *</label>
                            <input class="form-control" v-model="form.dni" required :disabled="saving">
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Apellido *</label>
                                <input class="form-control" v-model="form.last_name" required :disabled="saving">
                            </div>
                            <div class="col">
                                <label class="form-label">Nombre *</label>
                                <input class="form-control" v-model="form.first_name" required :disabled="saving">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input class="form-control" v-model="form.phone" :disabled="saving">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <input class="form-control" v-model="form.address" :disabled="saving">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Plan Asignado</label>
                            <select class="form-select" v-model="form.quota_plan_id" :disabled="saving">
                                <option value="">Sin plan</option>
                                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                    {{ plan.name }} - ${{ formatNumber(plan.amount) }} ({{ plan.frequency }})
                                </option>
                            </select>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enable" v-model="form.enable" :disabled="saving">
                            <label class="form-check-label" for="enable">Habilitado</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')" :disabled="saving">Cancelar</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            {{ partner ? 'Actualizar' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';

const props = defineProps({ partner: Object });
const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const plans = ref([]);

const formatNumber = (n) => parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });

const form = reactive({
    first_name: props.partner?.first_name || '',
    last_name: props.partner?.last_name || '',
    dni: props.partner?.dni || '',
    phone: props.partner?.phone || '',
    address: props.partner?.address || '',
    enable: props.partner?.enable !== undefined ? props.partner.enable : true,
    quota_plan_id: props.partner?.quota_plan_id || '',
});

const save = async () => {
    saving.value = true;
    try {
        if (props.partner) {
            await axios.put(`/quota/partners/${props.partner.id}`, form);
        } else {
            await axios.post('/quota/partners', form);
        }
        emit('saved');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al guardar');
    } finally {
        saving.value = false;
    }
};

const loadPlans = async () => {
    try {
        const { data } = await axios.get('/quota/plans');
        plans.value = data;
    } catch (e) { console.error(e); }
};

onMounted(loadPlans);
</script>

<style scoped>
.modal-dialog { width: 500px; max-width: 90%; }
</style>
