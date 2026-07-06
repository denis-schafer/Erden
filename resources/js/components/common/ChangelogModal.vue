<template>
    <div v-if="visible" class="modal d-flex align-items-center justify-content-center" @click.self="close" style="--bs-modal-margin:0">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0 rounded-top">
                    <h5 class="modal-title"><i class="bi bi-megaphone-fill me-2"></i>Novedades</h5>
                    <button class="btn-close btn-close-white" @click="close"></button>
                </div>
                <div class="modal-body bg-light" style="max-height:70vh;overflow-y:auto">
                    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                    <div v-else-if="!entries.length" class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-4 d-block mb-2"></i>
                        Sin novedades por el momento
                    </div>
                    <div v-for="entry in entries" :key="entry.id" class="card mb-3 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title mb-0 fw-bold">{{ entry.title }}</h6>
                                <small class="text-muted ms-2 text-nowrap">{{ formatDate(entry.created_at) }}</small>
                            </div>
                            <div v-html="entry.content" class="card-text small" style="white-space:pre-wrap"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 rounded-bottom">
                    <button class="btn btn-primary btn-sm" @click="close"><i class="bi bi-check2 me-1"></i>Entendido</button>
                </div>
            </div>
        </div>
    </div>
    <div v-if="visible" class="modal-backdrop fade show"></div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';

const props = defineProps({
    module: { type: String, default: '' },
    autoshow: { type: Boolean, default: false }
});

const emit = defineEmits(['close']);

const visible = ref(false);
const loading = ref(true);
const entries = ref([]);

const formatDate = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleDateString('es-AR', { day:'2-digit', month:'long', year:'numeric' });
};

const loadEntries = async () => {
    loading.value = true;
    try {
        const params = {};
        if (props.module) params.module = props.module;
        const res = await api.get('/changelog', { params });
        entries.value = res.data || [];
    } finally { loading.value = false; }
};

const STORAGE_KEY = 'changelog_last_seen_';

const shouldAutoShow = () => {
    if (!props.autoshow) return false;
    const published = entries.value.filter(e => e.is_published == 1 || e.is_published === true);
    if (!published.length) return false;
    const key = STORAGE_KEY + (props.module || 'all');
    const lastSeen = parseInt(localStorage.getItem(key) || '0');
    return published.some(e => e.id > lastSeen);
};

const markSeen = () => {
    const key = STORAGE_KEY + (props.module || 'all');
    const maxId = entries.value.length ? Math.max(...entries.value.map(e => e.id)) : 0;
    if (maxId > 0) localStorage.setItem(key, maxId.toString());
};

const close = () => {
    if (props.autoshow) markSeen();
    visible.value = false;
    emit('close');
};

onMounted(() => {
    loadEntries().then(() => {
        if (props.autoshow) {
            if (shouldAutoShow()) visible.value = true;
        } else {
            visible.value = true;
        }
    }).catch(() => {
        entries.value = [];
        visible.value = true;
    });
});

defineExpose({ open });
</script>
