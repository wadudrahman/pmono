<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Navigation Header -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-semibold text-gray-900">Pimono Wallet</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button
                            @click="toggleView"
                            :class="[
                                'px-4 py-2 rounded-md text-sm font-medium transition duration-200',
                                currentView === 'dashboard'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Dashboard
                        </button>
                        <button
                            @click="showSendMoney"
                            :class="[
                                'px-4 py-2 rounded-md text-sm font-medium transition duration-200',
                                currentView === 'send'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Send Money
                        </button>
                        <button
                            @click="showHistory"
                            :class="[
                                'px-4 py-2 rounded-md text-sm font-medium transition duration-200',
                                currentView === 'history'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            History
                        </button>
                        <span class="text-gray-700">{{ authStore.user?.name }}</span>
                        <button
                            @click="handleLogout"
                            class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">

                <!-- Dashboard View -->
                <div v-if="currentView === 'dashboard'">
                    <!-- Welcome Card -->
                    <div class="bg-white overflow-hidden shadow-xl rounded-lg">
                        <div class="px-6 py-4">
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                                Welcome back, {{ authStore.user?.name }}!
                            </h2>
                            <p class="text-gray-600">Email: {{ authStore.user?.email }}</p>
                        </div>
                    </div>

                    <!-- Balance Card -->
                    <div class="mt-6 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg shadow-xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-indigo-100 text-sm font-medium">Current Balance</p>
                                <p class="text-4xl font-bold mt-2">{{ formatCurrency(currentBalance) }}</p>
                            </div>
                            <button
                                @click="refreshBalance"
                                class="text-white hover:text-indigo-200 p-2 rounded-full transition duration-200"
                                :disabled="transactionStore.loading"
                            >
                                <svg :class="['w-6 h-6', transactionStore.loading && 'animate-spin']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            @click="showSendMoney"
                            class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition duration-200 text-left"
                        >
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </div>
                                    <div class="ml-5">
                                        <p class="text-sm font-medium text-gray-500">Send Money</p>
                                        <p class="mt-1 text-sm text-gray-900">Transfer to another user</p>
                                    </div>
                                </div>
                            </div>
                        </button>

                        <button
                            @click="showHistory"
                            class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition duration-200 text-left"
                        >
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="ml-5">
                                        <p class="text-sm font-medium text-gray-500">Transaction History</p>
                                        <p class="mt-1 text-sm text-gray-900">View all transactions</p>
                                    </div>
                                </div>
                            </div>
                        </button>

                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                    <div class="ml-5">
                                        <p class="text-sm font-medium text-gray-500">Settings</p>
                                        <p class="mt-1 text-sm text-gray-900">Manage account</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Transactions Preview -->
                    <div v-if="recentTransactions.length > 0" class="mt-6 bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Recent Transactions</h3>
                            <button
                                @click="showHistory"
                                class="text-blue-600 hover:text-blue-700 text-sm font-medium"
                            >
                                View All
                            </button>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <div
                                v-for="transaction in recentTransactions.slice(0, 3)"
                                :key="transaction.id"
                                class="px-6 py-4 flex items-center justify-between"
                            >
                                <div class="flex items-center space-x-3">
                                    <div
                                        :class="[
                                            'w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium',
                                            transaction.type === 'credit' ? 'bg-green-500' : 'bg-red-500'
                                        ]"
                                    >
                                        {{ transaction.type === 'credit' ? '↓' : '↑' }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ transaction.type === 'credit' ? 'From' : 'To' }}
                                            {{ transaction.type === 'credit' ? transaction.sender.name : transaction.receiver.name }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ formatDate(transaction.created_at) }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    :class="[
                                        'text-sm font-semibold',
                                        transaction.type === 'credit' ? 'text-green-600' : 'text-red-600'
                                    ]"
                                >
                                    {{ transaction.type === 'credit' ? '+' : '-' }}{{ formatCurrency(transaction.total) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Info Card -->
                    <div class="mt-6 bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Account Information</h3>
                        </div>
                        <div class="px-6 py-4">
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">User ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ authStore.user?.id }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ authStore.user?.name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ authStore.user?.email }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Account Balance</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ formatCurrency(currentBalance) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Send Money View -->
                <div v-else-if="currentView === 'send'" class="flex justify-center">
                    <TransferForm @success="handleTransferSuccess" />
                </div>

                <!-- Transaction History View -->
                <div v-else-if="currentView === 'history'">
                    <TransactionHistory />
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useTransactionStore } from '../stores/transactions'
import { transactionService } from '../services/transactions'
import TransferForm from './TransferForm.vue'
import TransactionHistory from './TransactionHistory.vue'

const router = useRouter()
const authStore = useAuthStore()
const transactionStore = useTransactionStore()

const currentView = ref('dashboard')

const currentBalance = computed(() => {
    return transactionStore.balance || authStore.user?.balance || '0.00'
})

const recentTransactions = computed(() => {
    return transactionStore.transactions || []
})

onMounted(async () => {
    try {
        await transactionStore.loadTransactions(1, true)

        // Set up real-time listeners for current user
        if (authStore.user?.id) {
            transactionStore.setupRealtimeListeners(authStore.user.id)
            // Request notification permission
            transactionStore.requestNotificationPermission()
        }
    } catch (error) {
        console.error('Failed to load transactions:', error)
    }
})

const showSendMoney = () => {
    currentView.value = 'send'
}

const showHistory = () => {
    currentView.value = 'history'
}

const toggleView = () => {
    currentView.value = 'dashboard'
}

const refreshBalance = async () => {
    try {
        await transactionStore.refreshTransactions()
    } catch (error) {
        console.error('Failed to refresh balance:', error)
    }
}

const handleTransferSuccess = () => {
    currentView.value = 'dashboard'
}

const formatCurrency = (amount) => {
    return transactionService.formatCurrency(amount)
}

const formatDate = (dateString) => {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

onUnmounted(() => {
    // Clean up real-time listeners when component is unmounted
    transactionStore.cleanupRealtimeListeners()
})

const handleLogout = async () => {
    await authStore.logout()
    router.push('/login')
}
</script>
