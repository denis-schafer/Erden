import { defineStore } from 'pinia';

let notificationId = 0;

export const useToastStore = defineStore('toast', {
    state: () => ({
        notifications: []
    }),

    actions: {
        add(message, type = 'info', duration = 5000) {
            const id = ++notificationId;
            this.notifications.push({ id, message, type });
            
            if (duration > 0) {
                setTimeout(() => {
                    this.remove(id);
                }, duration);
            }
        },

        success(message, duration = 5000) {
            this.add(message, 'success', duration);
        },

        error(message, duration = 5000) {
            this.add(message, 'error', duration);
        },

        warning(message, duration = 5000) {
            this.add(message, 'warning', duration);
        },

        info(message, duration = 5000) {
            this.add(message, 'info', duration);
        },

        remove(id) {
            const index = this.notifications.findIndex(n => n.id === id);
            if (index > -1) {
                this.notifications.splice(index, 1);
            }
        },

        clear() {
            this.notifications = [];
        }
    }
});