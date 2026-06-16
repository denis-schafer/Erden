<template>
    <div class="quota-plans p-3">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link" :class="{ active: activeTab === 'plans' }" href="#" @click.prevent="activeTab = 'plans'">
                    <i class="bi bi-calendar3 me-1"></i> Planes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" :class="{ active: activeTab === 'rates' }" href="#" @click.prevent="activeTab = 'rates'">
                    <i class="bi bi-tag me-1"></i> Tarifas Diarias
                </a>
            </li>
        </ul>

        <template v-if="activeTab === 'plans'">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Planes de Cuotas</h4>
                <button class="btn btn-primary btn-sm" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Nuevo Plan
                </button>
            </div>
            <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
            <template v-else>
                <div class="row g-3">
                    <div v-for="plan in plans" :key="plan.id" class="col-md-6 col-lg-4">
                        <div class="card h-100" :class="plan.is_active ? 'border-primary' : 'border-secondary'">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>{{ plan.name }}</span>
                                <span class="badge" :class="plan.is_active ? 'bg-primary' : 'bg-secondary'">
                                    {{ plan.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="mb-2"><strong>Frecuencia:</strong> {{ plan.frequency }}</div>
                                <div class="mb-2"><strong>Cuotas:</strong> {{ plan.installment_count }}</div>
                                <div class="mb-2"><strong>Importe:</strong> ${{ formatNumber(plan.amount) }}</div>
                                <div class="mb-2"><strong>Dcho Pileta:</strong> ${{ formatNumber(plan.pool_fee_amount) }} ({{ plan.pool_fee_count }})</div>
                                <hr>
                                <div class="d-flex justify-content-between text-muted small">
                                    <span>Socios: {{ plan.partners_count }}</span>
                                    <span>Cuotas: {{ plan.quotas_count }}</span>
                                    <span class="text-success">Pagadas: {{ plan.paid_count }}</span>
                                </div>
                            </div>
                            <div class="card-footer d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary flex-fill" @click="openEdit(plan)">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                                <button class="btn btn-sm btn-outline-success flex-fill" @click="generateQuotas(plan)" :disabled="generating === plan.id">
                                    <span v-if="generating === plan.id" class="spinner-border spinner-border-sm me-1"></span>
                                    <i class="bi bi-gear"></i> Generar
                                </button>
                                <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(plan)" :disabled="plan.quotas_count > 0">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="!plans.length" class="col-12 text-center text-muted py-5">No hay planes creados</div>
                </div>
            </template>
        </template>

        <template v-if="activeTab === 'rates'">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Tarifas Diarias</h4>
                <button class="btn btn-primary btn-sm" @click="openRateCreate">
                    <i class="bi bi-plus-lg"></i> Nueva Tarifa
                </button>
            </div>
            <div v-if="loadingRates" class="text-center py-5"><div class="spinner-border"></div></div>
            <div v-else class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="rate in dailyRates" :key="rate.id">
                            <td>{{ rate.name }}</td>
                            <td>${{ formatNumber(rate.amount) }}</td>
                            <td>
                                <span class="badge" :class="rate.is_active ? 'bg-success' : 'bg-secondary'">
                                    {{ rate.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1" @click="openRateEdit(rate)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" @click="confirmRateDelete(rate)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!dailyRates.length">
                            <td colspan="4" class="text-center text-muted">No hay tarifas diarias</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <div v-if="showForm" class="modal fade show d-block" tabindex="-1"
             style="background: rgba(0,0,0,0.5); z-index: 1060;"
             @click.self="showForm = false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingPlan ? 'Editar Plan' : 'Nuevo Plan' }}</h5>
                        <button type="button" class="btn-close" @click="showForm = false"></button>
                    </div>
                    <form @submit.prevent="savePlan">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nombre *</label>
                                <input class="form-control" v-model="form.name" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col">
                                    <label class="form-label">Frecuencia *</label>
                                    <select class="form-select" v-model="form.frequency" required>
                                        <option value="monthly">Mensual</option>
                                        <option value="bimonthly">Bimestral</option>
                                        <option value="quarterly">Trimestral</option>
                                        <option value="four_monthly">Cuatrimestral</option>
                                        <option value="biannual">Semestral</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col">
                                    <label class="form-label">Importe Cuota *</label>
                                    <input type="number" step="0.01" class="form-control" v-model.number="form.amount" required min="0">
                                </div>
                                <div class="col">
                                    <label class="form-label">Dcho Pileta *</label>
                                    <input type="number" step="0.01" class="form-control" v-model.number="form.pool_fee_amount" required min="0">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cant. Derechos de Pileta</label>
                                <input type="number" class="form-control" v-model.number="form.pool_fee_count" required min="1" max="12">
                            </div>
                            <div class="form-check" v-if="editingPlan">
                                <input class="form-check-input" type="checkbox" id="is_active" v-model="form.is_active">
                                <label class="form-check-label" for="is_active">Activo</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showForm = false">Cancelar</button>
                            <button type="submit" class="btn btn-primary" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                                {{ editingPlan ? 'Actualizar' : 'Crear' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div v-if="showYearModal" class="modal fade show d-block" tabindex="-1"
         style="background: rgba(0,0,0,0.5); z-index: 1060;"
         @click.self="showYearModal = false">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generar cuotas</h5>
                    <button type="button" class="btn-close" @click="showYearModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Año *</label>
                        <input type="number" class="form-control" v-model.number="yearInput" min="2020" max="2099" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showYearModal = false">Cancelar</button>
                    <button class="btn btn-primary" @click="confirmYearModal">Generar</button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="showRateForm" class="modal fade show d-block" tabindex="-1"
         style="background: rgba(0,0,0,0.5); z-index: 1060;"
         @click.self="showRateForm = false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editingRate ? 'Editar Tarifa Diaria' : 'Nueva Tarifa Diaria' }}</h5>
                    <button type="button" class="btn-close" @click="showRateForm = false"></button>
                </div>
                <form @submit.prevent="saveRate">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre *</label>
                            <input class="form-control" v-model="rateForm.name" required placeholder="Ej: Día Suelto, Semana...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto *</label>
                            <input type="number" step="0.01" class="form-control" v-model.number="rateForm.amount" required min="0">
                        </div>
                        <div class="form-check" v-if="editingRate">
                            <input class="form-check-input" type="checkbox" id="rate_is_active" v-model="rateForm.is_active">
                            <label class="form-check-label" for="rate_is_active">Activo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showRateForm = false">Cancelar</button>
                        <button type="submit" class="btn btn-primary" :disabled="savingRate">
                            <span v-if="savingRate" class="spinner-border spinner-border-sm me-1"></span>
                            {{ editingRate ? 'Actualizar' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <ConfirmModal ref="confirmModal" />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';
import ConfirmModal from '../../../components/ConfirmModal.vue';

const activeTab = ref('plans');

const plans = ref([]);
const loading = ref(true);
const showForm = ref(false);
const editingPlan = ref(null);
const saving = ref(false);
const generating = ref(null);
const confirmModal = ref(null);

const showYearModal = ref(false);
const yearInput = ref(new Date().getFullYear());
const generatePlan = ref(null);

const dailyRates = ref([]);
const loadingRates = ref(false);
const showRateForm = ref(false);
const editingRate = ref(null);
const savingRate = ref(false);
const rateForm = ref({ name: '', amount: 0, is_active: true });

const formatNumber = (n) => parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });
const form = ref({ name: '', frequency: 'monthly', amount: 0, pool_fee_amount: 0, pool_fee_count: 4 });

const loadPlans = async () => {
    try {
        const { data } = await axios.get('/quota/plans');
        plans.value = data;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const openCreate = () => {
    editingPlan.value = null;
    form.value = { name: '', frequency: 'monthly', amount: 0, pool_fee_amount: 0, pool_fee_count: 4, is_active: true };
    showForm.value = true;
};

const openEdit = (plan) => {
    editingPlan.value = plan;
    form.value = { ...plan };
    showForm.value = true;
};

const savePlan = async () => {
    saving.value = true;
    try {
        if (editingPlan.value) {
            await axios.put(`/quota/plans/${editingPlan.value.id}`, form.value);
        } else {
            await axios.post('/quota/plans', form.value);
        }
        showForm.value = false;
        loadPlans();
        toast.success(editingPlan.value ? 'Plan actualizado' : 'Plan creado');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al guardar');
    } finally { saving.value = false; }
};

const generateQuotas = (plan) => {
    generatePlan.value = plan;
    yearInput.value = new Date().getFullYear();
    showYearModal.value = true;
};

const confirmYearModal = () => {
    const year = yearInput.value;
    if (!year || isNaN(year) || year < 2020 || year > 2099) return;
    showYearModal.value = false;
    const plan = generatePlan.value;
    if (!confirmModal.value) return;
    confirmModal.value.open({
        title: 'Generar Cuotas',
        message: `¿Generar cuotas para el plan "${plan.name}" del año ${year}?`,
        confirmText: 'Generar',
        type: 'primary',
        onConfirm: async () => {
            generating.value = plan.id;
            try {
                const { data } = await axios.post(`/quota/plans/${plan.id}/generate`, { year: parseInt(year) });
                toast.success(data.message);
                loadPlans();
            } catch (e) {
                toast.error(e.response?.data?.message || 'Error al generar');
            } finally { generating.value = null; }
        }
    });
};

const confirmDelete = async (plan) => {
    if (confirmModal.value) {
        confirmModal.value.open({
            title: 'Eliminar Plan',
            message: `¿Eliminar plan "${plan.name}"?`,
            confirmText: 'Eliminar',
            type: 'danger',
            onConfirm: async () => {
                try {
                    await axios.delete(`/quota/plans/${plan.id}`);
                    loadPlans();
                    toast.success('Plan eliminado');
                } catch (e) {
                    toast.error(e.response?.data?.message || 'Error al eliminar');
                }
            }
        });
    }
};

const loadDailyRates = async () => {
    loadingRates.value = true;
    try {
        const { data } = await axios.get('/quota/daily-rates');
        dailyRates.value = data;
    } catch (e) { console.error(e); }
    finally { loadingRates.value = false; }
};

const openRateCreate = () => {
    editingRate.value = null;
    rateForm.value = { name: '', amount: 0, is_active: true };
    showRateForm.value = true;
};

const openRateEdit = (rate) => {
    editingRate.value = rate;
    rateForm.value = { name: rate.name, amount: rate.amount, is_active: rate.is_active };
    showRateForm.value = true;
};

const saveRate = async () => {
    savingRate.value = true;
    try {
        if (editingRate.value) {
            await axios.put(`/quota/daily-rates/${editingRate.value.id}`, rateForm.value);
        } else {
            await axios.post('/quota/daily-rates', rateForm.value);
        }
        showRateForm.value = false;
        loadDailyRates();
        toast.success(editingRate.value ? 'Tarifa actualizada' : 'Tarifa creada');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al guardar');
    } finally { savingRate.value = false; }
};

const confirmRateDelete = (rate) => {
    if (confirmModal.value) {
        confirmModal.value.open({
            title: 'Eliminar Tarifa',
            message: `¿Eliminar tarifa "${rate.name}"?`,
            confirmText: 'Eliminar',
            type: 'danger',
            onConfirm: async () => {
                try {
                    await axios.delete(`/quota/daily-rates/${rate.id}`);
                    loadDailyRates();
                    toast.success('Tarifa eliminada');
                } catch (e) {
                    toast.error(e.response?.data?.message || 'Error al eliminar');
                }
            }
        });
    }
};

onMounted(loadPlans);
</script>

<style scoped>
.modal-dialog { width: 550px; max-width: 90%; }
</style>
