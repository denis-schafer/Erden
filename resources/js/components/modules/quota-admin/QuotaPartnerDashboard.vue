<template>
    <div class="partner-dashboard">
        <div class="dashboard-header">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <div class="d-flex align-items-center gap-3">
                        <img v-if="portalLogo" :src="portalLogo" style="height: 40px;">
                        <div>
                            <h4 class="mb-0 text-white">Bienvenido, {{ user?.first_name }}</h4>
                            <small class="text-white-50">DNI: {{ user?.dni }}</small>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="Perfil">
                            <i class="bi bi-person"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" @click.prevent="showProfileModal = true"><i class="bi bi-pencil me-2"></i>Editar Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" @click.prevent="logout"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="container py-4">
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-white"></div>
            </div>
            <template v-else>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ summary.total }}</h3>
                                <small>Total Cuotas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ summary.paid }}</h3>
                                <small>Pagadas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ summary.pending }}</h3>
                                <small>Pendientes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">${{ formatNumber(summary.total_pending_amount) }}</h3>
                                <small>Deuda Total</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Mis Cuotas</span>
                        <input class="form-control form-control-sm search-input" v-model="search" placeholder="Buscar por plan, tipo, N°...">
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" @change="toggleAll" :checked="allPendingSelected"></th>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="q in filteredQuotas" :key="q.id" :class="q.status === 'paid' ? 'table-success' : ''">
                                        <td>
                                            <input type="checkbox" :checked="selectedQuotas.includes(q.id)" :value="q.id"
                                                :disabled="q.status !== 'pending' || (q.type === 'pool_fee' && !canPayPoolFee)"
                                                @change="toggleQuota(q.id)">
                                        </td>
                                        <td>{{ q.plan_name }} {{ q.year }}</td>
                                        <td>{{ q.type === 'pool_fee' ? 'Pileta' : 'Regular' }}</td>
                                        <td>{{ q.installment_number }}</td>
                                        <td>${{ formatNumber(q.amount) }}</td>
                                        <td>{{ q.due_date }}</td>
                                        <td>
                                            <span class="badge" :class="q.status === 'paid' ? 'bg-success' : 'bg-warning'">
                                                {{ q.status === 'paid' ? 'Pagada' : 'Pendiente' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!quotas.length">
                                        <td colspan="7" class="text-center text-muted">No hay cuotas</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button class="btn btn-primary" @click="paySelected" :disabled="!selectedQuotas.length || mpProcessing">
                            <span v-if="mpProcessing" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-wallet2 me-1"></i>
                            {{ mpProcessing ? 'Aguarde...' : 'Pagar $' + formatNumber(totalSelectedAmount) + ' con MercadoPago' }}
                        </button>
                    </div>

                </div>

                <div v-if="showProfileModal" class="modal fade show d-block" tabindex="-1"
                     style="background: rgba(0,0,0,0.5); z-index: 1060;"
                     @click.self="showProfileModal = false">
                    <div class="modal-dialog modal-sm modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Mi Perfil</h5>
                                <button type="button" class="btn-close" @click="showProfileModal = false"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <input class="form-control" v-model="profile.phone">
                                </div>
                                <button class="btn btn-primary btn-sm" @click="updateProfile">Guardar</button>
                                <hr>
                                <h6>Cambiar Contraseña</h6>
                                <input type="password" class="form-control form-control-sm mb-2" v-model="passwordForm.current" placeholder="Contraseña actual">
                                <input type="password" class="form-control form-control-sm mb-2" v-model="passwordForm.new_pass" placeholder="Nueva contraseña">
                                <button class="btn btn-warning btn-sm" @click="changePassword">Cambiar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';

const emit = defineEmits(['logout']);

const props = defineProps({
    portalConfig: { type: Object, default: () => ({}) },
});

const user = ref(null);
const quotas = ref([]);
const summary = ref({ total: 0, paid: 0, pending: 0, total_pending_amount: 0 });
const selectedQuotas = ref([]);
const loading = ref(true);
const mpProcessing = ref(false);
const portalLogo = ref('');
const showProfileModal = ref(false);
const profile = ref({ phone: '' });
const passwordForm = ref({ current: '', new_pass: '' });
const search = ref('');
const sortField = ref('');
const sortDir = ref('asc');

const allPendingSelected = computed(() => {
    const pending = quotas.value.filter(q => q.status === 'pending');
    return pending.length > 0 && pending.every(q => selectedQuotas.value.includes(q.id));
});

const canPayPoolFee = computed(() => {
    const pendingRegular = quotas.value.filter(q => q.type === 'regular' && q.status === 'pending');
    if (pendingRegular.length === 0) return true;
    return pendingRegular.every(q => selectedQuotas.value.includes(q.id));
});

const totalSelectedAmount = computed(() => {
    return quotas.value
        .filter(q => selectedQuotas.value.includes(q.id))
        .reduce((sum, q) => sum + Number(q.amount), 0);
});

const filteredQuotas = computed(() => {
    let result = [...quotas.value];

    if (search.value) {
        const s = search.value.toLowerCase();
        result = result.filter(q =>
            (q.plan_name && q.plan_name.toLowerCase().includes(s)) ||
            (q.type && q.type.toLowerCase().includes(s)) ||
            String(q.installment_number).includes(s) ||
            String(q.amount).includes(s) ||
            (q.due_date && q.due_date.includes(s))
        );
    }

    if (sortField.value) {
        result.sort((a, b) => {
            let valA, valB;
            switch (sortField.value) {
                case 'plan_name': valA = a.plan_name || ''; valB = b.plan_name || ''; break;
                case 'type': valA = a.type || ''; valB = b.type || ''; break;
                case 'installment_number': valA = Number(a.installment_number); valB = Number(b.installment_number); break;
                case 'amount': valA = Number(a.amount); valB = Number(b.amount); break;
                case 'due_date': valA = a.due_date || ''; valB = b.due_date || ''; break;
                case 'status': valA = a.status || ''; valB = b.status || ''; break;
                default: return 0;
            }
            if (valA < valB) return sortDir.value === 'asc' ? -1 : 1;
            if (valA > valB) return sortDir.value === 'asc' ? 1 : -1;
            return 0;
        });
    }

    return result;
});

const sortBy = (field) => {
    if (sortField.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDir.value = 'asc';
    }
};

const formatNumber = (n) => parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });

const apiClient = () => {
    const token = localStorage.getItem('quota_token');
    const companyDb = localStorage.getItem('quota_company_db');
    return axios.create({
        headers: {
            'Authorization': `Bearer ${token}`,
            'X-Company-Db': companyDb,
        }
    });
};

const loadData = async () => {
    try {
        const http = apiClient();
        const { data: userData } = await http.get('/asociados/user/current');
        user.value = userData;
        profile.value.phone = userData.phone || '';

        const { data: quotasData } = await http.get('/asociados/quotas');
        quotas.value = quotasData.quotas;
        summary.value = quotasData.summary;
    } catch (e) {
        if (e.response?.status === 401) emit('logout');
    } finally {
        loading.value = false;
    }
};

const toggleQuota = (id) => {
    const idx = selectedQuotas.value.indexOf(id);
    if (idx > -1) selectedQuotas.value.splice(idx, 1);
    else selectedQuotas.value.push(id);
};

const toggleAll = () => {
    if (allPendingSelected.value) selectedQuotas.value = [];
    else selectedQuotas.value = quotas.value.filter(q => q.status === 'pending').map(q => q.id);
};

const paySelected = async () => {
    mpProcessing.value = true;
    try {
        const http = apiClient();
        const { data } = await http.post('/asociados/mp/create-preference', {
            quota_ids: selectedQuotas.value,
        });
        if (data.init_point) {
            window.location.href = data.init_point;
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al crear pago');
    } finally {
        mpProcessing.value = false;
    }
};

const updateProfile = async () => {
    try {
        const http = apiClient();
        await http.put('/asociados/profile', { phone: profile.value.phone });
        toast.success('Perfil actualizado');
    } catch (e) {
        toast.error('Error al actualizar perfil');
    }
};

const changePassword = async () => {
    try {
        const http = apiClient();
        await http.post('/asociados/change-password', {
            current_password: passwordForm.value.current,
            new_password: passwordForm.value.new_pass,
        });
        toast.success('Contraseña actualizada');
        passwordForm.value = { current: '', new_pass: '' };
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al cambiar contraseña');
    }
};

const logout = () => {
    emit('logout');
};

onMounted(() => {
    portalLogo.value = props.portalConfig?.logo || '';
    loadData();
});
</script>

<style scoped>
.partner-dashboard {
    min-height: 100vh;
    min-height: 100dvh;
}
.dashboard-header {
    background: linear-gradient(135deg, var(--portal-primary, #667eea) 0%, var(--portal-secondary, #764ba2) 100%);
}
.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
.sortable:hover { background-color: rgba(0,0,0,0.05); }
.sort-indicator { font-size: 0.7rem; margin-left: 2px; }

.search-input {
    width: 220px;
    max-width: 45vw;
}
@media (max-width: 767.98px) {
    .partner-dashboard .container {
        padding-left: 10px;
        padding-right: 10px;
    }
    .search-input {
        width: 140px;
        max-width: 40vw;
    }
    :deep(.table-responsive) {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    :deep(.table) {
        min-width: 600px;
    }
}
</style>
