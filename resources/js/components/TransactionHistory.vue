<template>
  <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-900">Transaction History</h2>
      <button
        @click="refreshTransactions"
        :disabled="loading"
        class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-md text-sm transition duration-200"
      >
        <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span v-else>Refresh</span>
      </button>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md mb-4">
      <div class="flex justify-between items-center">
        <p class="text-sm">{{ error }}</p>
        <button @click="clearError" class="text-red-700 hover:text-red-900">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && !hasTransactions" class="text-center py-8">
      <svg class="animate-spin h-8 w-8 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="text-gray-500 mt-2">Loading transactions...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="!hasTransactions && !loading" class="text-center py-8">
      <svg class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mt-2">No transactions yet</h3>
      <p class="text-gray-500">Your transaction history will appear here once you start sending or receiving money.</p>
    </div>

    <!-- Transaction List -->
    <div v-else class="space-y-4">
      <div
        v-for="transaction in transactions"
        :key="transaction.id"
        class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition duration-150"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <!-- Transaction Icon -->
            <div
              :class="[
                'w-10 h-10 rounded-full flex items-center justify-center text-white font-medium',
                transaction.type === 'credit' ? 'bg-green-500' : 'bg-red-500'
              ]"
            >
              {{ transaction.type === 'credit' ? '↓' : '↑' }}
            </div>

            <!-- Transaction Details -->
            <div>
              <div class="flex items-center space-x-2">
                <h4 class="font-medium text-gray-900">
                  {{ transaction.type === 'credit' ? 'Received from' : 'Sent to' }}
                  {{ transaction.type === 'credit' ? transaction.sender.name : transaction.receiver.name }}
                </h4>
                <span
                  :class="[
                    'px-2 py-1 text-xs rounded-full',
                    transaction.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                  ]"
                >
                  {{ transaction.status }}
                </span>
              </div>
              <p class="text-sm text-gray-500">
                {{ transaction.type === 'credit' ? transaction.sender.email : transaction.receiver.email }}
              </p>
              <p v-if="transaction.description" class="text-sm text-gray-600 mt-1">
                {{ transaction.description }}
              </p>
            </div>
          </div>

          <!-- Amount and Date -->
          <div class="text-right">
            <div
              :class="[
                'font-semibold',
                transaction.type === 'credit' ? 'text-green-600' : 'text-red-600'
              ]"
            >
              {{ transaction.type === 'credit' ? '+' : '-' }}{{ formatCurrency(transaction.total) }}
            </div>
            <div v-if="transaction.type === 'debit' && transaction.commission_fee > 0" class="text-xs text-gray-500">
              Commission: {{ formatCurrency(transaction.commission_fee) }}
            </div>
            <div class="text-xs text-gray-500">
              {{ formatDate(transaction.created_at) }}
            </div>
            <div class="text-xs text-gray-400">
              Ref: {{ transaction.reference_number }}
            </div>
          </div>
        </div>
      </div>

      <!-- Load More Button -->
      <div v-if="hasMorePages" class="text-center pt-4">
        <button
          @click="loadMore"
          :disabled="loading"
          class="bg-gray-200 hover:bg-gray-300 disabled:bg-gray-100 text-gray-700 px-6 py-2 rounded-md text-sm transition duration-200"
        >
          <span v-if="loading">Loading...</span>
          <span v-else>Load More</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useTransactionStore } from '../stores/transactions'
import { transactionService } from '../services/transactions'

const transactionStore = useTransactionStore()

const transactions = computed(() => transactionStore.transactions)
const loading = computed(() => transactionStore.loading)
const error = computed(() => transactionStore.error)
const hasTransactions = computed(() => transactionStore.hasTransactions)
const hasMorePages = computed(() => transactionStore.hasMorePages)

onMounted(() => {
  if (!hasTransactions.value) {
    loadTransactions()
  }
})

const loadTransactions = async () => {
  try {
    await transactionStore.loadTransactions(1, true)
  } catch (error) {
    console.error('Failed to load transactions:', error)
  }
}

const refreshTransactions = async () => {
  await loadTransactions()
}

const loadMore = async () => {
  try {
    await transactionStore.loadMoreTransactions()
  } catch (error) {
    console.error('Failed to load more transactions:', error)
  }
}

const clearError = () => {
  transactionStore.clearError()
}

const formatCurrency = (amount) => {
  return transactionService.formatCurrency(amount)
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>