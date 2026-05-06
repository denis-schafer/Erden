<template>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h1 class="h4 mb-4">Dashboard</h1>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Empresa</h6>
                                <h5 class="mb-0">{{ authStore.company?.name || 'N/A' }}</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Usuario</h6>
                                <h5 class="mb-0">{{ authStore.user?.name || authStore.user?.username || 'N/A' }}</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Permisos</h6>
                                <h5 class="mb-0">{{ authStore.permissions.length }}</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Módulos</h6>
                                <h5 class="mb-0">{{ authStore.modules.length }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useAuthStore } from '../../stores/auth';

const authStore = useAuthStore();
</script>