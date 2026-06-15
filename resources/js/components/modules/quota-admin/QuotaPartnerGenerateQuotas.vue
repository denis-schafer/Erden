<template>
    <div class="modal fade show d-block" tabindex="-1"
         style="background: rgba(0,0,0,0.5); z-index: 1060;"
         @click.self="$emit('close')">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generar Cuotas - {{ partner.first_name }} {{ partner.last_name }}</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <div class="modal-body">
                    <div v-if="!plan" class="text-center py-3">
                        <p class="text-danger">Este socio no tiene un plan asignado.</p>
                        <p class="text-muted small">Asigne un plan desde la edición del socio antes de generar cuotas.</p>
                    </div>
                    <template v-else>
                        <div class="mb-3">
                            <strong>Plan asignado:</strong> {{ plan.name }}
                            <span class="badge bg-info ms-2">{{ plan.frequency }}</span>
                        </div>
                        <div class="mb-2">
                            <strong>Importe cuota:</strong> ${{ formatNumber(customAmount || plan.amount) }}
                            <span v-if="customAmount" class="text-muted small ms-2">(personalizado)</span>
                        </div>
                        <div class="mb-2">
                            <strong>Dcho pileta:</strong> ${{ formatNumber(customPoolFee || plan.pool_fee_amount) }} ({{ plan.pool_fee_count }} cuotas)
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Año *</label>
                            <input type="number" class="form-control" v-model.number="year" min="2020" max="2099" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cuotas a generar</label>
                            <div class="d-flex flex-wrap gap-2">
                                <label v-for="i in plan.installment_count" :key="i" class="installment-check">
                                    <input type="checkbox" :value="i" v-model="selectedInstallments">
                                    {{ i }}
                                </label>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="include_pool_fees" v-model="includePoolFees">
                            <label class="form-check-label" for="include_pool_fees">
                                Incluir derechos de pileta ({{ plan.pool_fee_count }} cuotas)
                            </label>
                        </div>
                    </template>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="$emit('close')" :disabled="generating">Cancelar</button>
                    <button type="button" class="btn btn-primary" @click="generate" :disabled="generating || !plan || selectedInstallments.length === 0">
                        <span v-if="generating" class="spinner-border spinner-border-sm me-1"></span>
                        {{ generating ? 'Generando...' : `Generar ${selectedInstallments.length} cuotas` }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';

const props = defineProps({ partner: Object });
const emit = defineEmits(['close', 'generated']);

const plan = ref(null);
const generating = ref(false);
const year = ref(new Date().getFullYear());
const selectedInstallments = ref([]);
const includePoolFees = ref(true);

const formatNumber = (n) => parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });

const customAmount = computed(() => props.partner?.custom_amount || null);
const customPoolFee = computed(() => props.partner?.custom_pool_fee_amount || null);

const loadPlan = async () => {
    if (!props.partner?.quota_plan_id) return;
    try {
        const { data: plans } = await axios.get('/quota/plans');
        const found = plans.find(p => p.id === props.partner.quota_plan_id);
        if (found) {
            plan.value = found;
            selectedInstallments.value = Array.from({ length: found.installment_count }, (_, i) => i + 1);
        }
    } catch (e) { console.error(e); }
};

const generate = async () => {
    if (!year.value || year.value < 2020 || year.value > 2099) {
        toast.warning('Año inválido');
        return;
    }
    generating.value = true;
    try {
        const { data } = await axios.post(`/quota/partners/${props.partner.id}/assign-quotas`, {
            quota_plan_id: plan.value.id,
            installments: selectedInstallments.value,
            include_pool_fees: includePoolFees.value,
            year: year.value,
        });
        toast.success(data.message);
        emit('generated');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al generar cuotas');
    } finally {
        generating.value = false;
    }
};

onMounted(loadPlan);
</script>

<style scoped>
.modal-dialog { width: 500px; max-width: 90%; }
.installment-check {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.85rem;
}
.installment-check input {
    width: 16px;
    height: 16px;
}
</style>
