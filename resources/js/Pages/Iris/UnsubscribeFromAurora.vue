<script setup lang="ts">
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"

// Props from Laravel
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

const urlParams = new URLSearchParams(window.location.search)
const a = ref(urlParams.get("a"))
const s = ref(urlParams.get("s"))

// Validate params
const isInvalidParams = computed(() => !a.value || !s.value)

function unsubscribe() {
  isProcessing.value = true
  errorMessage.value = null

  router.post(
    route("retina.models.unsubscribe_aurora"),
    { a: a.value, s: s.value },
    {
      onSuccess: () => {
        isSuccess.value = true
      },
      onError: () => {
        errorMessage.value = props.message.error
      },
      onFinish: () => {
        isProcessing.value = false
      },
    }
  )
}
</script>

<template>
  <div class="page-container">
    <div class="card">
      <!-- Page Title -->
      <h1 class="title">{{ title }}</h1>

      <!-- Invalid params -->
      <div v-if="isInvalidParams">
        <div class="alert alert-error">
          <h2 class="alert-title">Unable to Continue</h2>
          <p class="alert-text">
            You don’t have a valid ID to proceed with this request.
          </p>
        </div>
        <a href="/" class="btn btn-primary">Back to Home</a>
      </div>

      <!-- Success state -->
      <div v-else-if="isSuccess">
        <div class="alert alert-success">
          <h2 class="alert-title">{{ message.successTitle }}</h2>
          <p class="alert-text">{{ message.successDescription }}</p>
        </div>
      </div>

      <!-- Confirmation / Error state -->
      <div v-else>
        <h2 class="subtitle">{{ message.confirmationTitle }}</h2>

        <button
          class="btn btn-danger"
          :disabled="isProcessing"
          @click="unsubscribe"
        >
          <span v-if="!isProcessing">{{ message.button }}</span>
          <span v-else>Processing...</span>
        </button>

        <p v-if="errorMessage" class="alert alert-error mt">
          {{ errorMessage }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Layout */
.page-container {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  background: #f3f4f6;
  padding: 1rem;
}

.card {
  background: #fff;
  width: 100%;
  max-width: 500px;
  border-radius: 16px;
  padding: 2rem;
  text-align: center;
  height: fit-content;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

/* Typography */
.title {
  font-size: 2rem;
  font-weight: 800;
  color: #111827;
  margin-bottom: 1.5rem;
}

.subtitle {
  font-size: 1.125rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1.5rem;
}

.alert-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.alert-text {
  font-size: 0.95rem;
  color: #374151;
}

/* Alerts */
.alert {
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 1rem;
  border: 1px solid transparent;
}

.alert-error {
  background: #fef2f2;
  border-color: #fecaca;
  color: #dc2626;
}

.alert-success {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #16a34a;
}

/* Buttons */
.btn {
  display: inline-block;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.2s ease;
}

.btn-primary {
  background: #2563eb;
  color: #fff;
}

.btn-primary:hover {
  background: #1d4ed8;
}

.btn-danger {
  width: 100%;
  background: #dc2626;
  color: #fff;
  border: none;
}

.btn-danger:hover {
  background: #b91c1c;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Margin helper */
.mt {
  margin-top: 1rem;
}
</style>
