import { defineStore } from 'pinia';
import { transactionService } from '../services/transactions';
// Temporarily disable Echo to fix white screen issue
// import echo from '../services/echo';
import { useNotificationStore } from './notifications';

// Mock echo object to prevent errors
const echo = null;

export const useTransactionStore = defineStore('transactions', {
    state: () => ({
        transactions: [],
        currentPage: 1,
        lastPage: 1,
        total: 0,
        perPage: 20,
        balance: '0.00',
        loading: false,
        error: null,
        transferLoading: false,
        transferError: null,
        realtimeChannel: null,
        balancePollingInterval: null
    }),

    getters: {
        hasTransactions: (state) => state.transactions.length > 0,
        hasMorePages: (state) => state.currentPage < state.lastPage,
        formattedBalance: (state) => {
            return transactionService.formatCurrency(state.balance);
        }
    },

    actions: {
        /**
         * Load transaction history
         */
        async loadTransactions(page = 1, refresh = false) {
            if (refresh) {
                this.transactions = [];
                this.currentPage = 1;
            }

            this.loading = true;
            this.error = null;

            try {
                const response = await transactionService.getTransactions(page, this.perPage);

                // Update transactions
                if (page === 1 || refresh) {
                    this.transactions = response.transactions.data;
                } else {
                    this.transactions.push(...response.transactions.data);
                }

                // Update pagination info
                this.currentPage = response.transactions.current_page;
                this.lastPage = response.transactions.last_page;
                this.total = response.transactions.total;
                this.balance = response.balance;

                return response;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to load transactions';
                console.error('Failed to load transactions:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Load more transactions (pagination)
         */
        async loadMoreTransactions() {
            if (this.hasMorePages && !this.loading) {
                await this.loadTransactions(this.currentPage + 1);
            }
        },

        /**
         * Create a new transfer
         */
        async createTransfer(receiverId, amount, description = null) {
            this.transferLoading = true;
            this.transferError = null;

            try {
                const response = await transactionService.createTransfer(receiverId, amount, description);

                // Update balance
                this.balance = response.new_balance;

                // Prepend new transaction to list
                const newTransaction = {
                    ...response.transaction,
                    type: 'debit',
                    total: response.transaction.total_deducted,
                    sender: response.transaction.sender,
                    receiver: response.transaction.receiver
                };

                this.transactions.unshift(newTransaction);
                this.total++;

                return response;
            } catch (error) {
                this.transferError = error.response?.data?.error ||
                                  error.response?.data?.message ||
                                  'Transfer failed. Please try again.';
                console.error('Transfer failed:', error);
                throw error;
            } finally {
                this.transferLoading = false;
            }
        },

        /**
         * Clear transfer error
         */
        clearTransferError() {
            this.transferError = null;
        },

        /**
         * Clear general error
         */
        clearError() {
            this.error = null;
        },

        /**
         * Refresh transactions and balance
         */
        async refreshTransactions() {
            await this.loadTransactions(1, true);
        },

        /**
         * Add a received transaction (for real-time updates)
         */
        addReceivedTransaction(transaction) {
            // Convert to credit transaction from receiver's perspective
            const receivedTransaction = {
                ...transaction,
                type: 'credit',
                commission_fee: 0,
                total: transaction.amount
            };

            this.transactions.unshift(receivedTransaction);
            this.total++;

            // Update balance (will be updated from real-time event)
            this.balance = (parseFloat(this.balance) + parseFloat(transaction.amount)).toFixed(2);
        },

        /**
         * Update balance from real-time events
         */
        updateBalance(newBalance) {
            this.balance = newBalance;
        },

        /**
         * Set up real-time listeners for the current user
         */
        setupRealtimeListeners(userId) {
            if (this.realtimeChannel) {
                this.cleanupRealtimeListeners();
            }

            try {
                // Echo disabled temporarily - skip WebSocket setup
                if (echo && echo.private) {
                    this.realtimeChannel = echo.private(`user.${userId}`);

                    this.realtimeChannel.listen('TransactionProcessed', (event) => {
                        console.log('Real-time transaction received:', event);
                        this.handleRealtimeTransaction(event, userId);
                    });

                    console.log('Real-time listeners setup for user:', userId);
                } else {
                    console.log('Echo not available, using polling fallback only');
                }
            } catch (error) {
                console.error('Failed to setup real-time listeners:', error);
            }

            // Always setup polling fallback as WebSocket may fail silently
            this.setupPollingFallback(userId);
            console.log('Polling fallback activated for user:', userId);
        },

        /**
         * Setup polling fallback for balance updates when WebSocket fails
         */
        setupPollingFallback(userId) {
            console.log('Setting up polling fallback for balance updates');

            // Clear any existing polling
            if (this.balancePollingInterval) {
                clearInterval(this.balancePollingInterval);
            }

            // Poll for balance updates every 3 seconds when active
            this.balancePollingInterval = setInterval(async () => {
                console.log('Polling for balance updates...');
                await this.checkBalanceUpdate();
            }, 3000);

            // Initial check immediately
            this.checkBalanceUpdate();
        },

        /**
         * Check for balance updates
         */
        async checkBalanceUpdate() {
            try {
                const response = await transactionService.getTransactions(1, 1);
                const newBalance = response.balance;

                // Update balance if it changed
                if (this.balance !== newBalance) {
                    console.log('Balance updated:', this.balance, '->', newBalance);
                    const oldBalance = this.balance;
                    this.balance = newBalance;

                    // Show notification if balance increased (money received)
                    if (parseFloat(newBalance) > parseFloat(oldBalance)) {
                        const notificationStore = useNotificationStore();
                        const increase = (parseFloat(newBalance) - parseFloat(oldBalance)).toFixed(2);
                        notificationStore.success(
                            'Money Received!',
                            `Your balance increased by $${increase}`,
                            8000
                        );
                    }
                }
            } catch (error) {
                console.error('Balance update check failed:', error);
            }
        },

        /**
         * Handle real-time transaction updates
         */
        handleRealtimeTransaction(event, currentUserId) {
            const { transaction, balances } = event;
            const isReceiver = transaction.receiver.id === currentUserId;
            const isSender = transaction.sender.id === currentUserId;

            // Update balance based on user role
            if (isReceiver) {
                this.balance = balances.receiver;
                // Add as credit transaction
                this.addReceivedTransaction(transaction);
            } else if (isSender) {
                this.balance = balances.sender;
                // Transaction should already be in the list from the API call
                // But update balance in case of concurrent transactions
            }

            // Show notification
            this.showRealtimeNotification(transaction, isReceiver, isSender);
        },

        /**
         * Show real-time notification
         */
        showRealtimeNotification(transaction, isReceiver, isSender) {
            const amount = transactionService.formatCurrency(transaction.amount);
            const notificationStore = useNotificationStore();

            if (isReceiver) {
                // Show in-app notification
                notificationStore.success(
                    'Money Received!',
                    `You received ${amount} from ${transaction.sender.name}`,
                    8000
                );

                // Show browser notification
                if (typeof window !== 'undefined' && 'Notification' in window && Notification.permission === 'granted') {
                    new Notification('Money Received!', {
                        body: `You received ${amount} from ${transaction.sender.name}`,
                        icon: '/favicon.ico'
                    });
                }
            }
        },

        /**
         * Request notification permission
         */
        requestNotificationPermission() {
            if (typeof window !== 'undefined' && 'Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        },

        /**
         * Clean up real-time listeners
         */
        cleanupRealtimeListeners() {
            if (this.realtimeChannel && echo) {
                this.realtimeChannel.stopListening('TransactionProcessed');
                echo.leave(this.realtimeChannel.name);
                this.realtimeChannel = null;
                console.log('Real-time listeners cleaned up');
            }

            // Clear polling fallback
            if (this.balancePollingInterval) {
                clearInterval(this.balancePollingInterval);
                this.balancePollingInterval = null;
                console.log('Balance polling cleaned up');
            }
        },

        /**
         * Reset store state
         */
        reset() {
            this.cleanupRealtimeListeners();
            this.transactions = [];
            this.currentPage = 1;
            this.lastPage = 1;
            this.total = 0;
            this.balance = '0.00';
            this.loading = false;
            this.error = null;
            this.transferLoading = false;
            this.transferError = null;
            this.realtimeChannel = null;
            this.balancePollingInterval = null;
        }
    }
});