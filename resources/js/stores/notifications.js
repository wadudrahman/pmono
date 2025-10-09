import { defineStore } from 'pinia'

export const useNotificationStore = defineStore('notifications', {
    state: () => ({
        notifications: []
    }),

    actions: {
        addNotification(notification) {
            const id = Date.now()
            const newNotification = {
                id,
                type: 'info', // success, error, warning, info
                title: '',
                message: '',
                duration: 5000,
                ...notification
            }

            this.notifications.push(newNotification)

            // Auto remove after duration
            if (newNotification.duration > 0) {
                setTimeout(() => {
                    this.removeNotification(id)
                }, newNotification.duration)
            }

            return id
        },

        removeNotification(id) {
            const index = this.notifications.findIndex(n => n.id === id)
            if (index > -1) {
                this.notifications.splice(index, 1)
            }
        },

        clearAll() {
            this.notifications = []
        },

        // Convenience methods
        success(title, message, duration = 5000) {
            return this.addNotification({
                type: 'success',
                title,
                message,
                duration
            })
        },

        error(title, message, duration = 8000) {
            return this.addNotification({
                type: 'error',
                title,
                message,
                duration
            })
        },

        warning(title, message, duration = 6000) {
            return this.addNotification({
                type: 'warning',
                title,
                message,
                duration
            })
        },

        info(title, message, duration = 5000) {
            return this.addNotification({
                type: 'info',
                title,
                message,
                duration
            })
        }
    }
})