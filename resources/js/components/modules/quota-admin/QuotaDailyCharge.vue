<template>
    <div class="quota-daily-charge p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Cobro Diario</h4>
            <button class="btn btn-primary btn-sm" @click="openCreate">
                <i class="bi bi-plus-lg"></i> Nuevo Cobro
            </button>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <input class="form-control form-control-sm" v-model="filters.search" placeholder="Buscar por nombre o DNI...">
            </div>
            <div class="col-md-3">
                <input class="form-control form-control-sm" type="date" v-model="filters.date_from" placeholder="Desde">
            </div>
            <div class="col-md-3">
                <input class="form-control form-control-sm" type="date" v-model="filters.date_to" placeholder="Hasta">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" v-model="filters.rendered" @change="loadCharges">
                    <option value="">Todos</option>
                    <option value="false">Pendiente</option>
                    <option value="true">Rendido</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100" @click="loadCharges">Filtrar</button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Nombre</th>
                            <th>DNI</th>
                            <th>Tarifa</th>
                            <th>Monto</th>
                            <th>Rendido</th>
                            <th>Método</th>
                            <th>Cobrado por</th>
                            <th>Notas</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in charges" :key="c.id" :class="c.rendered ? 'table-success' : ''">
                            <td>{{ c.created_at }}</td>
                            <td>{{ c.person_name }}</td>
                            <td>{{ c.person_dni || '-' }}</td>
                            <td>{{ c.rate_name || '-' }}</td>
                            <td>${{ formatNumber(c.amount) }}</td>
                            <td>
                                <span v-if="c.rendered" class="text-success" title="Rendido por {{ c.rendered_by_name || '' }}">
                                    <i class="bi bi-check-circle-fill"></i>
                                </span>
                                <span v-else class="text-warning"><i class="bi bi-hourglass-split"></i></span>
                            </td>
                            <td>
                                <span class="badge" :class="c.payment_method === 'cash' ? 'bg-success' : 'bg-info'">
                                    {{ c.payment_method === 'cash' ? 'Efectivo' : 'Digital' }}
                                </span>
                            </td>
                            <td>{{ c.charged_by_name }}</td>
                            <td>{{ c.notes || '-' }}</td>
                            <td class="text-nowrap">
                                <button v-if="!c.rendered" class="btn btn-sm btn-outline-success" @click="renderCharge(c.id)" title="Rendir">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button v-else class="btn btn-sm btn-outline-secondary" @click="unrenderCharge(c.id)" title="Desrendir">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!charges.length">
                            <td colspan="10" class="text-center text-muted">No hay cobros diarios</td>
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
                        <h5 class="modal-title">Nuevo Cobro Diario</h5>
                        <button type="button" class="btn-close" @click="showForm = false"></button>
                    </div>
                    <form @submit.prevent="saveCharge">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Tarifa</label>
                                <select class="form-select" v-model="chargeForm.daily_rate_id" @change="onRateChange">
                                    <option value="">Seleccionar tarifa...</option>
                                    <option v-for="r in rates" :key="r.id" :value="r.id">
                                        {{ r.name }} - ${{ formatNumber(r.amount) }}
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nombre de la persona *</label>
                                <input class="form-control" v-model="chargeForm.person_name" required placeholder="Nombre y apellido">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">DNI (opcional)</label>
                                <input class="form-control" v-model="chargeForm.person_dni" placeholder="Número de documento">
                            </div>
                            <div class="row mb-3">
                                <div class="col-4">
                                    <label class="form-label">Cantidad</label>
                                    <input type="number" class="form-control" v-model.number="chargeForm.quantity" min="1" @input="updateAmount">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Monto *</label>
                                    <input type="number" step="0.01" class="form-control" v-model.number="chargeForm.amount" required min="0">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Método de pago *</label>
                                    <select class="form-select" v-model="chargeForm.payment_method">
                                        <option value="cash">Efectivo</option>
                                        <option value="digital">Digital</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notas (opcional)</label>
                                <textarea class="form-control" v-model="chargeForm.notes" rows="2" placeholder="Observaciones..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showForm = false">Cancelar</button>
                            <button type="submit" class="btn btn-primary" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                                Registrar Cobro
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';

const charges = ref([]);
const rates = ref([]);
const loading = ref(true);
const showForm = ref(false);
const saving = ref(false);

const filters = reactive({
    search: '',
    date_from: '',
    date_to: '',
    rendered: '',
});

const chargeForm = reactive({
    daily_rate_id: '',
    person_name: '',
    person_dni: '',
    quantity: 1,
    amount: 0,
    payment_method: 'cash',
    notes: '',
});

const formatNumber = (n) => parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });

const loadCharges = async () => {
    loading.value = true;
    try {
        const params = {};
        if (filters.search) params.search = filters.search;
        if (filters.date_from) params.date_from = filters.date_from;
        if (filters.date_to) params.date_to = filters.date_to;
        if (filters.rendered !== '') params.rendered = filters.rendered;
        const { data } = await axios.get('/quota/daily-charges', { params });
        charges.value = data.data || data;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const loadRates = async () => {
    try {
        const { data } = await axios.get('/quota/daily-rates');
        rates.value = data;
    } catch (e) { console.error(e); }
};

const onRateChange = () => {
    const rate = rates.value.find(r => r.id === Number(chargeForm.daily_rate_id));
    if (rate) {
        chargeForm.quantity = 1;
        chargeForm.amount = rate.amount;
    }
};

const updateAmount = () => {
    const rate = rates.value.find(r => r.id === Number(chargeForm.daily_rate_id));
    if (rate && chargeForm.quantity > 0) {
        chargeForm.amount = rate.amount * chargeForm.quantity;
    }
};

const openCreate = () => {
    chargeForm.daily_rate_id = '';
    chargeForm.person_name = '';
    chargeForm.person_dni = '';
    chargeForm.quantity = 1;
    chargeForm.amount = 0;
    chargeForm.payment_method = 'cash';
    chargeForm.notes = '';
    showForm.value = true;
};

const saveCharge = async () => {
    saving.value = true;
    try {
        await axios.post('/quota/daily-charges', {
            daily_rate_id: chargeForm.daily_rate_id || null,
            person_name: chargeForm.person_name,
            person_dni: chargeForm.person_dni || null,
            quantity: chargeForm.quantity || null,
            amount: chargeForm.amount,
            payment_method: chargeForm.payment_method,
            notes: chargeForm.notes || null,
        });
        showForm.value = false;
        loadCharges();
        toast.success('Cobro diario registrado');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al registrar');
    } finally { saving.value = false; }
};

const renderCharge = async (id) => {
    try {
        await axios.post(`/quota/daily-charges/${id}/render`);
        loadCharges();
        toast.success('Cobro rendido correctamente');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al rendir');
    }
};

const unrenderCharge = async (id) => {
    try {
        await axios.post(`/quota/daily-charges/${id}/unrender`);
        loadCharges();
        toast.success('Rendición revertida');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al revertir');
    }
};

onMounted(() => {
    loadCharges();
    loadRates();
});
</script>

<style scoped>
.table th { white-space: nowrap; }
</style>
