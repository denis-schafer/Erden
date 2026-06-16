<template>
    <div class="quota-items p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Cuotas</h4>
            <div>
                <button class="btn btn-success btn-sm me-2" @click="showPayModal = true" :disabled="!selectedIds.length">
                    <i class="bi bi-cash"></i> Cobrar Seleccionadas ({{ selectedIds.length }})
                </button>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <input class="form-control form-control-sm" v-model="filters.search" placeholder="Buscar..." @input="onFilterChange">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" v-model="filters.status" @change="onFilterChange">
                    <option value="">Todos</option>
                    <option value="pending">Pendientes</option>
                    <option value="paid">Pagadas</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" v-model="filters.type" @change="onFilterChange">
                    <option value="">Todos</option>
                    <option value="regular">Regulares</option>
                    <option value="pool_fee">Dcho Pileta</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" v-model="filters.rendered" @change="onFilterChange">
                    <option value="">Todos</option>
                    <option value="false">No Rendidas</option>
                    <option value="true">Rendidas</option>
                </select>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" @change="toggleAll" :checked="allSelected"></th>
                            <th class="sortable" @click="sortBy('dni')">
                                DNI <span v-if="sortField === 'dni'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('last_name')">
                                Socio <span v-if="sortField === 'last_name'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('plan_name')">
                                Plan <span v-if="sortField === 'plan_name'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('type')">
                                Tipo <span v-if="sortField === 'type'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('installment_number')">
                                N° <span v-if="sortField === 'installment_number'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('amount')">
                                Importe <span v-if="sortField === 'amount'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('due_date')">
                                Vencimiento <span v-if="sortField === 'due_date'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('status')">
                                Estado <span v-if="sortField === 'status'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th class="sortable" @click="sortBy('payment_method')">
                                Pago <span v-if="sortField === 'payment_method'" class="sort-indicator">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                            </th>
                            <th>Rendido</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items.data" :key="item.id" :class="item.rendered ? 'table-success' : (item.status === 'paid' ? 'table-info' : '')">
                            <td>
                                <input type="checkbox" :checked="selectedIds.includes(item.id)" :value="item.id"
                                    :disabled="item.status !== 'pending'"
                                    @change="toggleItem(item.id)">
                            </td>
                            <td>{{ item.dni }}</td>
                            <td>{{ item.last_name }}, {{ item.first_name }}</td>
                            <td>{{ item.plan_name }} {{ item.year }}</td>
                            <td>{{ item.type === 'pool_fee' ? 'Pileta' : 'Regular' }}</td>
                            <td>{{ item.installment_number }}</td>
                            <td>${{ formatNumber(item.amount) }}</td>
                            <td>{{ item.due_date }}</td>
                            <td>
                                <span class="badge" :class="item.status === 'paid' ? 'bg-success' : 'bg-warning'">
                                    {{ item.status === 'paid' ? 'Pagada' : 'Pendiente' }}
                                </span>
                            </td>
                            <td>{{ item.payment_method || '-' }}</td>
                            <td>
                                <span v-if="item.rendered" class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                                <span v-else class="text-warning"><i class="bi bi-hourglass-split"></i></span>
                            </td>
                            <td>
                                <button v-if="item.status === 'paid'" class="btn btn-sm" :class="item.rendered ? 'btn-outline-warning' : 'btn-outline-info'"
                                    @click="toggleRendered(item)" title="Rendir/Desrendir">
                                    <i class="bi" :class="item.rendered ? 'bi-arrow-return-left' : 'bi-check2-square'"></i>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!items.data?.length">
                            <td colspan="12" class="text-center text-muted">No hay cuotas</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <nav v-if="items.last_page > 1">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: items.current_page === 1 }">
                            <button class="page-link" @click="changePage(items.current_page - 1)">Anterior</button>
                        </li>
                        <li class="page-item" :class="{ active: page === items.current_page }" v-for="page in items.last_page" :key="page">
                            <button class="page-link" @click="changePage(page)">{{ page }}</button>
                        </li>
                        <li class="page-item" :class="{ disabled: items.current_page === items.last_page }">
                            <button class="page-link" @click="changePage(items.current_page + 1)">Siguiente</button>
                        </li>
                    </ul>
                </nav>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="text-muted small">Mostrar:</span>
                    <select class="form-select form-select-sm" style="width: auto;" v-model.number="perPage" @change="onPerPageChange">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>
        </template>

        <div v-if="showPayModal" class="modal fade show d-block" tabindex="-1"
             style="background: rgba(0,0,0,0.5); z-index: 1060;"
             @click.self="showPayModal = false">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Cobrar Cuotas</h5>
                        <button type="button" class="btn-close" @click="showPayModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Cuotas a cobrar:</strong> {{ selectedIds.length }}</p>
                        <div class="mb-3">
                            <label class="form-label">Método de pago *</label>
                            <select class="form-select" v-model="paymentMethod" required>
                                <option value="cash">Efectivo</option>
                                <option value="digital">Digital</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="renderNow" v-model="renderNow">
                            <label class="form-check-label" for="renderNow">Rendir ahora</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="showPayModal = false">Cancelar</button>
                        <button class="btn btn-success" @click="processPayment" :disabled="paying">
                            <span v-if="paying" class="spinner-border spinner-border-sm me-1"></span>
                            Cobrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';

const items = ref({ data: [], current_page: 1, last_page: 1 });
const loading = ref(true);
const selectedIds = ref([]);
const showPayModal = ref(false);
const paymentMethod = ref('cash');
const renderNow = ref(false);
const paying = ref(false);
const filters = ref({ search: '', status: '', type: '', rendered: '' });
const perPage = ref(10);
const currentPage = ref(1);
const sortField = ref('');
const sortDir = ref('asc');

const allSelected = computed(() => items.value.data?.length > 0 && items.value.data.every(i => i.status !== 'pending' || selectedIds.value.includes(i.id)));

const formatNumber = (n) => parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });

const loadItems = async () => {
    loading.value = true;
    selectedIds.value = [];
    try {
        const params = {
            ...filters.value,
            page: currentPage.value,
            per_page: perPage.value,
        };
        if (sortField.value) {
            params.sort_field = sortField.value;
            params.sort_dir = sortDir.value;
        }
        Object.keys(params).forEach(k => { if (!params[k] && k !== 'page' && k !== 'per_page') delete params[k]; });
        const { data } = await axios.get('/quota/items', { params });
        items.value = data;
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const changePage = (page) => {
    if (page < 1 || page > items.value.last_page) return;
    currentPage.value = page;
    loadItems();
};

const onFilterChange = () => {
    currentPage.value = 1;
    loadItems();
};

const onPerPageChange = () => {
    currentPage.value = 1;
    loadItems();
};

const sortBy = (field) => {
    if (sortField.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDir.value = 'asc';
    }
    currentPage.value = 1;
    loadItems();
};

const toggleItem = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) selectedIds.value.splice(idx, 1);
    else selectedIds.value.push(id);
};

const toggleAll = () => {
    if (allSelected.value) selectedIds.value = [];
    else selectedIds.value = items.value.data.filter(i => i.status === 'pending').map(i => i.id);
};

const toggleRendered = async (item) => {
    try {
        await axios.post(`/quota/items/${item.id}/toggle-rendered`);
        loadItems();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error');
    }
};

const processPayment = async () => {
    paying.value = true;
    try {
        await axios.post('/quota/items/pay', {
            quota_ids: selectedIds.value,
            payment_method: paymentMethod.value,
            rendered: renderNow.value,
        });
        showPayModal.value = false;
        loadItems();
        toast.success('Pago procesado correctamente');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al procesar pago');
    } finally { paying.value = false; }
};

onMounted(() => {
    loadItems();
    setupEcho();
    window.addEventListener('view-changed', onViewChanged);
});

let echoChannel = null;
let echoRetryTimer = null;

function setupEcho() {
    const companyDb = localStorage.getItem('quota_company_db');
    if (!companyDb) return;
    if (!window.Echo) {
        echoRetryTimer = setTimeout(setupEcho, 500);
        return;
    }
    echoChannel = window.Echo.private('quota.' + companyDb);
    echoChannel.listen('.QuotaRenderedUpdated', (e) => {
        if (!e.quota_ids || !e.quota_ids.length) return;
        for (const item of items.value.data || []) {
            if (e.quota_ids.includes(item.id)) {
                item.rendered = e.rendered;
            }
        }
    });
}

function onViewChanged(e) {
    if (e.detail === 'quota-items') {
        loadItems();
    }
}

onUnmounted(() => {
    if (echoRetryTimer) clearTimeout(echoRetryTimer);
    if (echoChannel) {
        echoChannel.stopListening('.QuotaRenderedUpdated');
        window.Echo.leaveChannel('quota.' + localStorage.getItem('quota_company_db'));
        echoChannel = null;
    }
    window.removeEventListener('view-changed', onViewChanged);
});
</script>

<style scoped>
.modal-dialog { width: 400px; max-width: 90%; }
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { background-color: rgba(0,0,0,0.05); }
.sort-indicator { font-size: 0.7rem; margin-left: 2px; }
</style>
