<template>
    <div class="pos-log-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Log del Sistema</h4>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Filtros</h6>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Módulo</label>
                        <select v-model="tempModule" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option v-for="mod in availableModules" :key="mod" :value="mod">
                                {{ mod }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Acción</label>
                        <select v-model="tempAction" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <option v-for="act in availableActions" :key="act" :value="act">
                                {{ act }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" v-model="tempStartDate" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" v-model="tempEndDate" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100" @click="applyFilter">
                            <i class="bi bi-filter me-1"></i>Filtrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Logs -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Registros ({{ logs.length }})</h6>
            </div>
            <div class="card-body p-0">
                <div v-if="loading" class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                </div>
                <div v-else-if="logs.length === 0" class="text-center py-5 text-muted">
                    No hay registros para mostrar
                </div>
                <div v-else class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th>Fecha/Hora</th>
                                <th v-if="!selectedModule">Módulo</th>
                                <th>Acción</th>
                                <th>Detalles</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(log, index) in logs" :key="index">
                                <td class="text-nowrap">{{ log.timestamp }}</td>
                                <td v-if="!selectedModule">
                                    <span class="badge bg-secondary">{{ log.module || getModuleName(log) }}</span>
                                </td>
                                <td>
                                    <span :class="getActionBadgeClass(log.action)" class="badge">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td>{{ log.details }}</td>
                                <td>
                                    <span v-if="log.username && log.username !== 'N/A'" class="badge bg-info">
                                        {{ log.username }}
                                    </span>
                                    <span v-else class="text-muted">N/A</span>
                                    <small class="text-muted">(ID: {{ log.user_id }})</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../services/api';

const logs = ref([]);
const availableModules = ref([]);
const availableActions = ref([]);
// Temporary filter values (not bound directly to load)
const tempModule = ref('');
const tempAction = ref('');
const tempStartDate = ref(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
const tempEndDate = ref(new Date().toISOString().split('T')[0]);
// Actual values used for loading
const selectedModule = ref('');
const selectedAction = ref('');
const startDate = ref(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);
const loading = ref(false);

const loadLogs = async () => {
    loading.value = true;
    try {
        const params = {
            module: selectedModule.value,
        };
        
        if (startDate.value) params.start_date = startDate.value;
        if (endDate.value) params.end_date = endDate.value;
        if (selectedAction.value) params.action = selectedAction.value;
        
        const response = await api.get('/pos/log/entries', { params });
        
        logs.value = response.data.logs || [];
        availableModules.value = response.data.available_modules || [];
        // Only get actions if a specific module is selected
        if (selectedModule.value) {
            availableActions.value = response.data.actions || [];
        } else {
            availableActions.value = [];
        }
        
    } finally {
        loading.value = false;
    }
};

const applyFilter = () => {
    // Copy temporary values to actual filter values
    selectedModule.value = tempModule.value;
    selectedAction.value = tempAction.value;
    startDate.value = tempStartDate.value;
    endDate.value = tempEndDate.value;
    loadLogs();
};

const getModuleName = (log) => {
    if (log.module && log.module !== 'unknown') return log.module;
    // Try to guess from action
    if (log.action.includes('product_')) return 'productos';
    if (log.action.includes('user_')) return 'usuarios';
    return 'N/A';
};

const getActionBadgeClass = (action) => {
    if (action.includes('enabled')) return 'bg-success';
    if (action.includes('disabled')) return 'bg-danger';
    if (action.includes('created')) return 'bg-primary';
    if (action.includes('updated') || action.includes('edited')) return 'bg-warning text-dark';
    return 'bg-secondary';
};

onMounted(() => {
    loadLogs();
});
</script>

<style scoped>
.pos-log-container {
    height: 100%;
    padding: 1rem;
    overflow-y: auto;
    background: #f8f9fa;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    background: white;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
}

.table th {
    font-weight: 600;
    font-size: 0.875rem;
}

.sticky-top {
    position: sticky;
    top: 0;
    z-index: 1;
}

.badge {
    font-size: 0.75rem;
}
</style>
