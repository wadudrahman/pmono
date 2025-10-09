<template>
  <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Send Money</h2>

    <form @submit.prevent="handleSubmit" class="space-y-4">
      <!-- Receiver ID Field -->
      <div>
        <label for="receiverId" class="block text-sm font-medium text-gray-700 mb-2">
          Recipient User ID
        </label>
        <input
          id="receiverId"
          v-model="form.receiverId"
          type="number"
          required
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          :disabled="loading"
        />
      </div>

      <!-- Amount Field -->
      <div>
        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
          Amount (USD)
        </label>
        <input
          id="amount"
          v-model="form.amount"
          type="number"
          step="0.01"
          min="0.01"
          max="999999.99"
          required
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          :disabled="loading"
          @input="calculatePreview"
        />
      </div>

      <!-- Commission Preview -->
      <div v-if="preview && form.amount" class="bg-gray-50 p-3 rounded-md">
        <div class="text-sm text-gray-600 space-y-1">
          <div class="flex justify-between">
            <span>Amount:</span>
            <span>{{ formatCurrency(preview.amount) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Commission (1.5%):</span>
            <span>{{ formatCurrency(preview.commission) }}</span>
          </div>
          <div class="flex justify-between font-medium text-gray-900 border-t pt-1">
            <span>Total Deduction:</span>
            <span>{{ formatCurrency(preview.total) }}</span>
          </div>
        </div>
      </div>

      <!-- Description Field -->
      <div>
        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
          Description (Optional)
        </label>
        <textarea
          id="description"
          v-model="form.description"
          rows="3"
          maxlength="255"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
          :disabled="loading"
        ></textarea>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
        <p class="text-sm">{{ error }}</p>
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        :disabled="loading || !form.receiverId || !form.amount"
        class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium py-2 px-4 rounded-md transition duration-200"
      >
        <span v-if="loading" class="inline-flex items-center">
          <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Processing...
        </span>
        <span v-else>Send Money</span>
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useTransactionStore } from '../stores/transactions'
import { transactionService } from '../services/transactions'

const transactionStore = useTransactionStore()

const form = ref({
  receiverId: '',
  amount: '',
  description: ''
})

const preview = ref(null)

const loading = computed(() => transactionStore.transferLoading)
const error = computed(() => transactionStore.transferError)

const emit = defineEmits(['success'])

watch(() => form.value.amount, () => {
  calculatePreview()
})

const calculatePreview = () => {
  if (form.value.amount && parseFloat(form.value.amount) > 0) {
    preview.value = transactionService.calculateCommission(form.value.amount)
  } else {
    preview.value = null
  }
}

const formatCurrency = (amount) => {
  return transactionService.formatCurrency(amount)
}

const handleSubmit = async () => {
  if (!form.value.receiverId || !form.value.amount) return

  transactionStore.clearTransferError()

  try {
    await transactionStore.createTransfer(
      parseInt(form.value.receiverId),
      parseFloat(form.value.amount),
      form.value.description || null
    )

    // Reset form on success
    form.value = {
      receiverId: '',
      amount: '',
      description: ''
    }
    preview.value = null

    emit('success')

  } catch (error) {
    console.error('Transfer failed:', error)
  }
}
</script>