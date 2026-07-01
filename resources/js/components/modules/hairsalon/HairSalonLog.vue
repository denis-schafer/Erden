<template>
    <div class="hairsalon-log p-3">
        <h4 class="mb-3">Log de Actividad</h4>
        <div class="row g-2 mb-3">
            <div class="col-auto"><input class="form-control form-control-sm" type="date" v-model="startDate"></div>
            <div class="col-auto"><input class="form-control form-control-sm" type="date" v-model="endDate"></div>
            <div class="col-auto"><select class="form-select form-select-sm" v-model="filterAction"><option value="">Todas las acciones</option><option v-for="a in actions" :key="a" :value="a">{{ a }}</option></select></div>
            <div class="col-auto"><button class="btn btn-outline-primary btn-sm" @click="loadLogs">Filtrar</button></div>
        </div>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <div v-else>
            <DataTable :data="logs" :columns="columns" :per-page="20" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';

const loading = ref(true);
const logs = ref([]);
const actions = ref([]);
const startDate = ref('');
const endDate = ref('');
const filterAction = ref('');

const columns = [
    { key: 'timestamp', label: 'Fecha/Hora' },
    { key: 'action', label: 'Acción' },
    { key: 'message', label: 'Mensaje' },
];

const loadLogs = async () => {
    loading.value = true;
    try {
        const res = await api.get('/hairsalon/log/entries', { params: { start_date: startDate.value, end_date: endDate.value, action: filterAction.value } });
        logs.value = res.data.logs || [];
        actions.value = res.data.actions || [];
    } finally { loading.value = false; }
};

onMounted(() => { loadLogs(); });
</script>
