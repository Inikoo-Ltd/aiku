<script setup lang="ts">
import { ref, computed } from "vue"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"

// Props dari Laravel
const props = defineProps<{
  title: string
  message: {
    confirmationTitle: string
    button: string
    successTitle: string
    successDescription: string
    error: string
    invalidParamsTitle: string
    invalidParamsDesc: string
    backHome: string
  }
}>()

const isProcessing = ref(false)
const isSuccess = ref(false)
const errorMessage = ref<string | null>(null)

// Recipient details (nullable)
const recipientEmail = ref<string | null>(null)
const recipientName = ref<string | null>(null)

// Ambil params dari URL
const urlParams = new URLSearchParams(window.location.search)
const a = ref(urlParams.get("a"))
const s = ref(urlParams.get("s"))

const isInvalidParams = computed(() => !a.value || !s.value)

// Dynamic page title
const pageTitle = computed(() => {
  if (isInvalidParams.value) return props.message.invalidParamsTitle
  if (isSuccess.value) return props.message.successTitle
  if (errorMessage.value) return props.message.error
  return props.title
})

async function unsubscribe() {
  if (isInvalidParams.value) return

  isProcessing.value = true
  errorMessage.value = null

  try {
    const { data } = await axios.post(route("iris.models.unsubscribe_aurora"), {
      a: a.value,
      s: s.value,
    })

    if (data.api_response_status == 200) {
      isSuccess.value = true
      recipientEmail.value = data.api_response_data.recipient_email ?? null
      recipientName.value = data.api_response_data.recipient_name ?? null
    } else {
      errorMessage.value = data.message || props.message.error
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'failed to unsubscribe'
  } finally {
    isProcessing.value = false
  }
}

function goHome() {
  window.location.href = "/"
}
</script>

<template>
  <div class="page-wrapper">
    <div class="card">
      <!-- Dynamic Title -->
      <h1
        class="page-title"
        :class="[isSuccess ? 'text-green-600' : 'text-gray-900'  ]"
      >
        {{ pageTitle }}
      </h1>

      <!-- Invalid Params -->
      <div v-if="isInvalidParams" class="state-wrapper">
        <div class="state-box error">
          <p>{{ message.invalidParamsDesc }}</p>
        </div>

        <Button @click="goHome" full :label="message.backHome" />
      </div>

      <!-- Success -->
      <div v-else-if="isSuccess" class="state-wrapper">
        <!-- Success description -->
        <p class="success-desc">{{ message.successDescription }}</p>

        <!-- Recipient info -->
        <table class="info-table">
          <tbody>
            <tr>
              <td class="label">{{ trans("Recipient Email") }}</td>
              <td class="value">{{ recipientEmail }}</td>
            </tr>
            <tr>
              <td class="label">{{ trans("Name") }}</td>
              <td class="value">{{ recipientName }}</td>
            </tr>
          </tbody>
        </table>
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
          :label="message.button"
        />

        <p v-if="errorMessage" class="state-box error small">
          {{ errorMessage }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-wrapper {
  @apply min-h-screen flex justify-center bg-gradient-to-br from-gray-50 to-gray-100 p-6;
}

.card {
  @apply bg-white w-full max-w-xl rounded-3xl p-10 text-center shadow-lg border border-gray-200 h-fit;
}

.page-title {
  @apply mb-4 text-3xl font-extrabold  tracking-tight transition-colors;
}

.state-wrapper {
  @apply space-y-6;
}

.state-box {
  @apply p-6 rounded-2xl border text-sm leading-relaxed;
}

.state-box.error {
  @apply border-red-200 bg-red-50 text-red-700;
}

.state-box.small {
  @apply text-xs p-4;
}

.confirm-title {
  @apply text-base font-medium text-gray-700;
}

/* Success description */
.success-desc {
  @apply text-base text-green-700 leading-relaxed mb-4;
}

/* Table for recipient info */
.info-table {
  @apply w-full mt-2 border border-gray-200 rounded-lg overflow-hidden text-sm;
}

.info-table td {
  @apply px-4 py-2 border-b border-gray-100;
}

.info-table .label {
  @apply font-medium text-gray-700 bg-gray-50 w-1/3;
}

.info-table .value {
  @apply text-gray-900;
}

.info-table tr:last-child td {
  @apply border-b-0;
}
</style>
