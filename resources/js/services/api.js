import axios from 'axios';

const api = axios.create({
    baseURL: '',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

api.interceptors.request.use(config => {
    const token = localStorage.getItem('token');
    const permissions = localStorage.getItem('permissions');
    const isGlobalAdmin = localStorage.getItem('isGlobalAdmin');
    const companyDb = localStorage.getItem('companyDb');
    const isParentDb = localStorage.getItem('isParentDb');
    const posToken = localStorage.getItem('pos_token');
    
    if (config.url?.startsWith('/pos/')) {
        // Use posToken if available, otherwise use regular token
        const authToken = posToken || token;
        if (authToken) {
            config.headers.Authorization = `Bearer ${authToken}`;
        }
        if (companyDb) {
            config.headers['X-Company-Db'] = companyDb;
        }
    } else {
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        if (permissions) {
            config.headers['X-Permissions'] = permissions;
        }
        if (isGlobalAdmin) {
            config.headers['X-Is-Global-Admin'] = isGlobalAdmin;
        }
        if (companyDb) {
            config.headers['X-Company-Db'] = companyDb;
        }
        if (isParentDb) {
            config.headers['X-Is-Parent-Db'] = isParentDb;
        }
    }
    return config;
});

api.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 401) {
            // Don't redirect if it's a login request or POS request - let the component handle the error
            const url = error.config?.url || '';
            const isLoginRequest = url === '/login' || url === '/check-user';
            const isPosRequest = url.startsWith('/pos/');
            
            if (!isLoginRequest && !isPosRequest) {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                localStorage.removeItem('permissions');
                localStorage.removeItem('modules');
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;