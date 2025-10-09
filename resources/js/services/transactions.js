import axios from 'axios';

const API_URL = '/api/v1/transactions';

// Create axios instance with default config
const api = axios.create({
    baseURL: window.location.origin,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
});

// Add token to requests if it exists
api.interceptors.request.use(config => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Handle 401 responses
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export const transactionService = {
    /**
     * Get transaction history with pagination
     */
    async getTransactions(page = 1, perPage = 20) {
        const response = await api.get(`${API_URL}?page=${page}&per_page=${perPage}`);
        return response.data;
    },

    /**
     * Create a new transfer
     */
    async createTransfer(receiverId, amount, description = null) {
        const response = await api.post(API_URL, {
            receiver_id: receiverId,
            amount: parseFloat(amount),
            description
        });
        return response.data;
    },

    /**
     * Search for users by email or name (if we add this endpoint later)
     */
    async searchUsers(query) {
        // TODO: Implement user search endpoint
        // For now, users need to know the receiver_id
        return [];
    },

    /**
     * Calculate commission for preview
     */
    calculateCommission(amount) {
        const commission = parseFloat(amount) * 0.015;
        return {
            amount: parseFloat(amount),
            commission: Math.round(commission * 100) / 100,
            total: Math.round((parseFloat(amount) + commission) * 100) / 100
        };
    },

    /**
     * Format currency for display
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    },

    /**
     * Format transaction type for display
     */
    getTransactionTypeDisplay(type) {
        return {
            credit: { text: 'Received', class: 'text-green-600', icon: '↓' },
            debit: { text: 'Sent', class: 'text-red-600', icon: '↑' }
        }[type] || { text: 'Unknown', class: 'text-gray-600', icon: '?' };
    }
};