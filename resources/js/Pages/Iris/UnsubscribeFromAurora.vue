<script setup lang="ts">
import { ref, computed } from "vue"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"

// Props dari Laravel
const props = defineProps<{
  title: string
  message: {
    confirmationTitle: string
    button: string
    successTitle: string
    successDescription: string
    error: string
  }
}>()

const isProcessing = ref(false)
const isSuccess = ref(false)
const errorMessage = ref<string | null>(null)

// Ambil params dari URL
const urlParams = new URLSearchParams(window.location.search)
const a = ref(urlParams.get("a"))
const s = ref(urlParams.get("s"))

const isInvalidParams = computed(() => !a.value || !s.value)

async function unsubscribe() {
  if (isInvalidParams.value) return

  isProcessing.value = true
  errorMessage.value = null

  try {
    const { data } = await axios.post(route('iris.unsubscribe.unsubscribe_aurora'), {
      a: a.value,
      s: s.value,
    })

    if (data.success) {
      isSuccess.value = true
    } else {
      errorMessage.value = data.message || props.message.error
    }
  } catch (err: any) {
    errorMessage.value =
      err.response?.data?.message || props.message.error
  } finally {
    isProcessing.value = false
  }
}
</script>


<template>
  <div class="page-wrapper">
    <div class="card">
      <!-- Title -->
      <h1 class="page-title">{{ title }}</h1>

      <!-- Invalid Params -->
      <div v-if="isInvalidParams" class="state-wrapper">
        <div class="state-box error">
          <h2>Unable to Continue</h2>
          <p>You don’t have a valid ID to proceed with this request.</p>
        </div>
        <a href="/" class="btn-primary">Back to Home</a>
      </div>

      <!-- Success -->
      <div v-else-if="isSuccess" class="state-wrapper">
        <div class="state-box success">
          <h2>{{ message.successTitle }}</h2>
          <p>{{ message.successDescription }}</p>
        </div>
      </div>

      <!-- Confirmation -->
      <div v-else class="state-wrapper">
        <h2 class="confirm-title">{{ message.confirmationTitle }}</h2>

        <Button
          :type="'negative'"
          :disabled="isProcessing"
          @click="unsubscribe"
          :loading="isProcessing"
          full
        >
          <span>{{ message.button }}</span>
        </Button>

        <p v-if="errorMessage" class="state-box error small">
          {{ errorMessage }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-wrapper {
  @apply min-h-screen flex  justify-center bg-gradient-to-br from-gray-50 to-gray-100 p-6;
}

.card {
  @apply bg-white w-full max-w-xl rounded-3xl p-10 text-center shadow-lg border border-gray-200 h-fit;
}

.page-title {
  @apply mb-2 text-3xl font-extrabold text-gray-900 tracking-tight;
}

.state-wrapper {
  @apply space-y-6;
}

.state-box {
  @apply p-6 rounded-2xl border text-sm leading-relaxed;
}

.state-box.success {
  @apply border-green-200 bg-green-50 text-green-700;
}

.state-box.error {
  @apply border-red-200 bg-red-50 text-red-700;
}

.state-box.small {
  @apply text-xs p-4;
}

.btn-primary {
  @apply inline-block px-6 py-3 rounded-xl font-medium transition bg-blue-600 text-white hover:bg-blue-700;
}

.btn-danger {
  @apply !rounded-xl;
}

.confirm-title {
  @apply text-base font-medium text-gray-700;
}
</style>
