import { toast as toastify } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

export const toast = {
    success(message, duration = 5000) {
        toastify.success(message, {
            autoClose: duration,
            position: 'top-right'
        });
    },
    error(message, duration = 5000) {
        toastify.error(message, {
            autoClose: duration,
            position: 'top-right'
        });
    },
    warning(message, duration = 5000) {
        toastify.warning(message, {
            autoClose: duration,
            position: 'top-right'
        });
    },
    info(message, duration = 5000) {
        toastify.info(message, {
            autoClose: duration,
            position: 'top-right'
        });
    }
};
