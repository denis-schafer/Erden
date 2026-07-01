<template>
    <div class="hairsalon-cashier p-3">
        <h4 class="mb-3">Caja - Cobro de Trabajos</h4>
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card"><div class="card-body">
                    <h5 class="card-title">Cliente</h5>
                    <div class="position-relative mb-3">
                        <input v-model="clientSearch" class="form-control form-control-sm" placeholder="Buscar cliente..." @input="onClientInput" @focus="clientDropdown = true" @blur="onClientBlur" ref="clientInputRef">
                        <div v-if="selectedClient" class="mt-1 p-1 border rounded bg-light d-flex justify-content-between align-items-center">
                            <small><strong>{{ selectedClient.name }}</strong> <span class="text-muted">{{ selectedClient.phone }}</span></small>
                            <button class="btn btn-sm p-0 text-danger" @click="selectedClientId = null; clientSearch = ''; selectedClient = null" title="Quitar cliente"><i class="bi bi-x"></i></button>
                        </div>
                        <div v-if="clientDropdown && filteredClients.length" class="position-absolute w-100 border rounded bg-white shadow-sm" style="max-height:200px;overflow-y:auto;z-index:1050">
                            <div v-for="c in filteredClients" :key="c.id" class="px-2 py-1" @mousedown.prevent="selectClient(c)" style="cursor:pointer;border-bottom:1px solid #f0f0f0" @mouseover="clientHover = c.id" :class="clientHover === c.id ? 'bg-primary text-white' : ''">
                                {{ c.name }} <small class="text-muted">{{ c.phone }}</small>
                            </div>
                            <div class="px-2 py-1 border-top small text-primary" @mousedown.prevent="quickAddClient" style="cursor:pointer">
                                <i class="bi bi-plus"></i> Agregar rápido
                            </div>
                        </div>
                    </div>

                    <h5 class="card-title">Servicios</h5>
                    <div class="position-relative">
                        <input v-model="serviceSearch" class="form-control form-control-sm" placeholder="Buscar servicio..." @input="onServiceInput" @focus="serviceDropdown = true" @blur="onServiceBlur" ref="serviceInputRef">
                        <div v-if="serviceDropdown && filteredServices.length" class="position-absolute w-100 border rounded bg-white shadow-sm" style="max-height:250px;overflow-y:auto;z-index:1050">
                            <div v-for="s in filteredServices" :key="s.id" class="px-2 py-1" @mousedown.prevent="toggleService(s)" style="cursor:pointer;border-bottom:1px solid #f0f0f0" @mouseover="serviceHover = s.id" :class="[serviceHover === s.id ? 'bg-primary text-white' : '', isServiceSelected(s) ? 'bg-light fw-bold' : '']">
                                <span>{{ s.name }} <small>${{ formatNumber(s.price) }}</small></span>
                                <small v-if="productsForService(s).length" class="text-info d-block"><i class="bi bi-box-seam"></i> {{ productsForService(s).length }} producto(s) vinculado(s)</small>
                            </div>
                        </div>
                        <div v-if="!filteredServices.length && serviceSearch" class="mt-1 text-muted small">Sin servicios que coincidan</div>
                    </div>
                    <div class="mt-1">
                        <span v-for="item in selectedItems" :key="item.id" class="badge bg-primary me-1 mb-1">
                            {{ item.name }} <span class="ms-1 text-warning">${{ formatNumber(item.price) }}</span>
                            <button class="btn btn-sm p-0 ms-1 text-white" @click="removeService(item)"><i class="bi bi-x"></i></button>
                        </span>
                        <span v-if="!selectedItems.length" class="text-muted small">Sin servicios seleccionados</span>
                    </div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <h5 class="card-title">Resumen</h5>
                    <table class="table table-sm">
                        <thead><tr><th>Servicio</th><th class="text-end">Precio</th></tr></thead>
                        <tbody>
                            <tr v-for="item in selectedItems" :key="item.id">
                                <td>{{ item.name }}</td>
                                <td class="text-end">
                                    <input v-model.number="item.price" class="form-control form-control-sm text-end" type="number" step="0.01" min="0" style="width:90px;display:inline">
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr><td><strong>Subtotal</strong></td><td class="text-end"><strong>${{ formatNumber(subtotal) }}</strong></td></tr>
                            <tr><td>Descuento</td><td class="text-end">
                                <input v-model.number="discount" class="form-control form-control-sm text-end" type="number" min="0" style="width:90px;display:inline">
                            </td></tr>
                            <tr><td><strong>Total</strong></td><td class="text-end"><strong class="text-success">${{ formatNumber(total) }}</strong></td></tr>
                        </tfoot>
                    </table>

                    <div class="mb-2 p-2 border rounded bg-light">
                        <h6 class="mb-2"><i class="bi bi-box-seam"></i> Productos a descontar</h6>
                        <div v-for="d in deductibleItems" :key="d.product_id" class="d-flex align-items-center gap-2 mb-1">
                            <span class="flex-grow-1 small">{{ d.product_name }}</span>
                            <small class="text-muted">stock: {{ d.stock }}</small>
                            <input v-model.number="d.quantity" class="form-control form-control-sm" type="number" min="0" :max="d.stock" style="width:55px">
                            <button class="btn btn-sm p-0 text-danger" @click="removeDeductible(d.product_id)" title="Quitar"><i class="bi bi-x"></i></button>
                        </div>
                        <div v-if="!deductibleItems.length" class="text-muted small mb-2">Seleccioná servicios o agregá productos manualmente</div>
                        <div class="mt-1 position-relative">
                            <input v-model="manualSearch" class="form-control form-control-sm" placeholder="Buscar producto..." @input="onManualInput" @focus="showDropdown = true" @blur="onBlur" ref="manualInputRef">
                            <div v-if="showDropdown && filteredProducts.length" class="position-absolute w-100 border rounded bg-white shadow-sm" style="max-height:180px;overflow-y:auto;z-index:1050">
                                <div v-for="p in filteredProducts" :key="p.id" class="px-2 py-1 small" @mousedown.prevent="selectProduct(p)" style="cursor:pointer;border-bottom:1px solid #f0f0f0" @mouseover="hoverIdx = p.id" :class="hoverIdx === p.id ? 'bg-primary text-white' : ''">
                                    {{ p.name }} <small class="text-muted">(stock: {{ p.quantity }})</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2"><label class="form-label">Método de Pago</label>
                        <select v-model="paymentMethod" class="form-select form-select-sm">
                            <option value="cash">Efectivo</option><option value="transfer">Transferencia</option>
                            <option value="mercadopago">MercadoPago</option><option value="other">Otro</option>
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label">Notas</label><textarea v-model="notes" class="form-control form-control-sm" rows="2"></textarea></div>
                    <button class="btn btn-success w-100" @click="charge" :disabled="!canCharge || charging">
                        {{ charging ? 'Cobrando...' : 'Cobrar $' + formatNumber(total) }}
                    </button>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <h5 class="card-title">Trabajos del Día</h5>
                    <DataTable v-if="todayJobs.length" :data="todayJobsDisplay" :columns="jobsColumns" :per-page="8" />
                    <p v-else class="text-muted small mb-0">Sin trabajos hoy</p>
                </div></div>
            </div>
        </div>

        <div v-if="showQuickClient" class="modal d-block"><div class="modal-dialog modal-sm"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Nuevo Cliente</h5><button class="btn-close" @click="showQuickClient = false"></button></div>
            <form @submit.prevent="addQuickClient"><div class="modal-body">
                <div class="mb-2"><input v-model="quickClientName" class="form-control" placeholder="Nombre" required></div>
                <div class="mb-2"><input v-model="quickClientPhone" class="form-control" placeholder="Teléfono"></div>
            </div><div class="modal-footer">
                <button type="submit" class="btn btn-primary btn-sm">Agregar</button>
            </div></form>
        </div></div></div>
        <div v-if="showQuickClient" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import { useCache } from '../../../composables/useCache';
import { toast } from '../../../utils/toast';

const { fetch, refresh } = useCache();

// Client
const clientSearch = ref('');
const allClients = ref([]);
const filteredClients = ref([]);
const selectedClientId = ref(null);
const selectedClient = ref(null);
const clientDropdown = ref(false);
const clientHover = ref(null);
const clientInputRef = ref(null);

// Services
const serviceSearch = ref('');
const services = ref([]);
const selectedItems = ref([]);
const serviceDropdown = ref(false);
const serviceHover = ref(null);
const serviceInputRef = ref(null);

// Products
const products = ref([]);
const deductibleItems = ref([]);
const manualSearch = ref('');
const showDropdown = ref(false);
const hoverIdx = ref(null);
const manualInputRef = ref(null);

const discount = ref(0);
const paymentMethod = ref('cash');
const notes = ref('');
const charging = ref(false);
const todayJobs = ref([]);
const showQuickClient = ref(false);
const quickClientName = ref('');
const quickClientPhone = ref('');

// Computed
const filteredProducts = computed(() => {
    const inList = new Set(deductibleItems.value.map(d => d.product_id));
    const s = manualSearch.value.toLowerCase();
    return products.value.filter(p => !inList.has(p.id) && (!s || p.name.toLowerCase().includes(s)));
});

const variosSvc = { id: 'varios', name: 'Varios', price: 0, category_id: null, product_id: null, duration_min: null, is_active: true };

const filteredServices = computed(() => {
    const s = serviceSearch.value.toLowerCase();
    let list = [...services.value];
    if (!s || 'varios'.includes(s)) {
        list.unshift(variosSvc);
    }
    return list.filter(svc => !s || svc.name.toLowerCase().includes(s));
});

const subtotal = computed(() =>
    selectedItems.value.reduce((sum, item) => sum + Number(item.price || 0), 0)
);

const total = computed(() => Math.max(0, subtotal.value - discount.value));

const canCharge = computed(() =>
    selectedClientId.value && selectedItems.value.length > 0 && total.value > 0
);

const jobsColumns = [
    { key: 'client_name', label: 'Cliente' },
    { key: 'total_display', label: 'Total' },
    { key: 'method_display', label: 'Método' },
];

const todayJobsDisplay = computed(() => {
    return todayJobs.value.slice(0, 50).map(j => ({
        ...j,
        total_display: '$' + formatNumber(j.total),
        method_display: methodLabel(j.payment_method),
    }));
});

const methodLabel = (m) => ({ cash: 'Efectivo', transfer: 'Transferencia', mercadopago: 'MercadoPago', other: 'Otro' }[m] || m);
const formatNumber = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Client dropdown
const onClientInput = () => {
    const s = clientSearch.value.toLowerCase();
    filteredClients.value = allClients.value.filter(c =>
        c.name.toLowerCase().includes(s) || (c.phone && c.phone.includes(s))
    );
    clientDropdown.value = true;
};

const onClientBlur = () => {
    setTimeout(() => { clientDropdown.value = false; }, 200);
};

const selectClient = (c) => {
    selectedClientId.value = c.id;
    selectedClient.value = c;
    clientSearch.value = c.name;
    clientDropdown.value = false;
};

const quickAddClient = () => {
    showQuickClient.value = true;
    quickClientName.value = clientSearch.value;
};

const addQuickClient = async () => {
    try {
        const res = await api.post('/hairsalon/clients', { name: quickClientName.value, phone: quickClientPhone.value });
        allClients.value.push(res.data.client);
        selectClient(res.data.client);
        showQuickClient.value = false;
        quickClientName.value = '';
        quickClientPhone.value = '';
        toast.success('Cliente creado');
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
};

// Service dropdown
const onServiceInput = () => {
    serviceDropdown.value = true;
};

const onServiceBlur = () => {
    setTimeout(() => { serviceDropdown.value = false; }, 200);
};

const isServiceSelected = (svc) => selectedItems.value.some(item => item.id === svc.id);

const toggleService = (svc) => {
    const idx = selectedItems.value.findIndex(item => item.id === svc.id);
    if (idx >= 0) {
        selectedItems.value.splice(idx, 1);
    } else {
        selectedItems.value.push({
            id: svc.id,
            name: svc.name,
            price: Number(svc.price),
            category_id: svc.category_id,
            product_id: svc.product_id,
        });
    }
    rebuildDeductibles();
};

const removeService = (item) => {
    const idx = selectedItems.value.findIndex(i => i.id === item.id);
    if (idx >= 0) selectedItems.value.splice(idx, 1);
    rebuildDeductibles();
};

// Products dropdown (manual add)
const onManualInput = () => { showDropdown.value = true; };
const onBlur = () => { setTimeout(() => { showDropdown.value = false; }, 200); };

const selectProduct = (p) => {
    selectManualProduct(p);
    manualSearch.value = '';
    showDropdown.value = false;
    if (manualInputRef.value) manualInputRef.value.blur();
};

const selectManualProduct = (p) => {
    manualSearch.value = '';
    const existing = deductibleItems.value.find(d => d.product_id === p.id);
    if (existing) {
        existing.quantity = (existing.quantity || 0) + 1;
    } else {
        deductibleItems.value.push({
            product_id: p.id,
            product_name: p.name,
            stock: p.quantity,
            quantity: 1,
            _manual: true,
        });
    }
};

const removeDeductible = (productId) => {
    const idx = deductibleItems.value.findIndex(d => d.product_id === productId);
    if (idx >= 0) deductibleItems.value.splice(idx, 1);
};

const productsForService = (svc) => {
    return products.value.filter(p => {
        if (svc.product_id && p.id === svc.product_id) return true;
        if (p.service_id && p.service_id === svc.id) return true;
        return false;
    });
};

const rebuildDeductibles = () => {
    const seen = new Set(deductibleItems.value.filter(d => d._manual).map(d => d.product_id));
    const result = deductibleItems.value.filter(d => d._manual);
    for (const item of selectedItems.value) {
        const matched = productsForService(item);
        for (const p of matched) {
            if (!seen.has(p.id)) {
                seen.add(p.id);
                const existing = deductibleItems.value.find(d => d.product_id === p.id);
                result.push({
                    product_id: p.id,
                    product_name: p.name,
                    stock: p.quantity,
                    quantity: existing ? existing.quantity : 0,
                });
            }
        }
    }
    deductibleItems.value = result;
};

const loadInitial = async () => {
    try {
        const [svcData, clientsData, dashboardData, productsData] = await Promise.all([
            fetch('hairsalon-services', () => api.get('/hairsalon/services').then(r => r.data)),
            fetch('hairsalon-clients-all', () => api.get('/hairsalon/clients', { params: { per_page: 200 } }).then(r => r.data)),
            api.get('/hairsalon/dashboard').then(r => r.data),
            fetch('hairsalon-products-all', () => api.get('/hairsalon/products', { params: { per_page: 500 } }).then(r => r.data)),
        ]);
        services.value = (svcData.services || []).filter(s => s.is_active);
        products.value = ((productsData.products || productsData).data || productsData.data || []);
        allClients.value = clientsData.data || [];
        filteredClients.value = allClients.value;
        const recentJobs = dashboardData.recent_jobs || [];
        todayJobs.value = recentJobs.filter(j =>
            new Date(j.created_at).toDateString() === new Date().toDateString()
        );
    } catch (e) { /* silent */ }
};

const charge = async () => {
    if (!canCharge.value) return;
    charging.value = true;
    try {
        const payload = {
            client_id: selectedClientId.value,
            services: selectedItems.value.map(item => ({ id: item.id, price: item.price })),
            deductions: deductibleItems.value.filter(d => d.quantity > 0).map(d => ({ product_id: d.product_id, quantity: d.quantity })),
            discount: discount.value,
            payment_method: paymentMethod.value,
            notes: notes.value,
        };
        const res = await api.post('/hairsalon/cashier', payload);
        toast.success('Cobro realizado exitosamente');
        if (res.data.deducted_products && res.data.deducted_products.length) {
            window.dispatchEvent(new CustomEvent('hairsalon-stock-changed'));
        }
        selectedItems.value = [];
        deductibleItems.value = [];
        discount.value = 0;
        notes.value = '';
        const client = allClients.value.find(c => c.id === selectedClientId.value);
        todayJobs.value.unshift({
            ...res.data.job,
            client_name: client?.name || 'Cliente',
            operator_name: 'Yo',
        });
        window.dispatchEvent(new CustomEvent('hairsalon-job-created'));
    } catch (e) { toast.error(e.response?.data?.message || 'Error al cobrar'); }
    finally { charging.value = false; }
};

const reloadData = async () => {
    const [clientsData, svcData, productsData] = await Promise.all([
        refresh('hairsalon-clients-all', () => api.get('/hairsalon/clients', { params: { per_page: 200 } }).then(r => r.data)),
        refresh('hairsalon-services', () => api.get('/hairsalon/services').then(r => r.data)),
        refresh('hairsalon-products-all', () => api.get('/hairsalon/products', { params: { per_page: 500 } }).then(r => r.data)),
    ]);
    allClients.value = clientsData.data || [];
    filteredClients.value = allClients.value;
    services.value = (svcData.services || []).filter(s => s.is_active);
    products.value = ((productsData.products || productsData).data || productsData.data || []);
    rebuildDeductibles();
};

const handleClientChanged = () => { reloadData(); };
const handleServiceChanged = () => { reloadData(); };
const handleStockChanged = () => { reloadData(); };

onMounted(() => {
    loadInitial();
    window.addEventListener('hairsalon-client-changed', handleClientChanged);
    window.addEventListener('hairsalon-service-changed', handleServiceChanged);
    window.addEventListener('hairsalon-stock-changed', handleStockChanged);
});
onUnmounted(() => {
    window.removeEventListener('hairsalon-client-changed', handleClientChanged);
    window.removeEventListener('hairsalon-service-changed', handleServiceChanged);
    window.removeEventListener('hairsalon-stock-changed', handleStockChanged);
});
</script>
