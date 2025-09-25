<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { routeType } from "@/types/route";

// Props from Laravel
const props = defineProps<{
  title: string
  keys: Record<string, string>
  route_unsubscribe : routeType
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

function unsubscribe(keys: Record<string, string>) {
  isProcessing.value = true
  errorMessage.value = null

  router.post(
    route(props.route_unsubscribe.name,{keys}),
    keys,
    {
      onSuccess: () => {
        isSuccess.value = true
        isProcessing.value = false
      },
      onError: () => {
        errorMessage.value = props.message.error
        isProcessing.value = false
      }
    }
  )
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 p-6">
    <div class="w-full max-w-lg bg-white shadow-md rounded-2xl p-6 text-center">
      <h1 class="text-2xl font-bold mb-4">{{ title }}</h1>

      <!-- Success state -->
      <div v-if="isSuccess">
        <h2 class="text-xl font-semibold text-green-600 mb-2">
          {{ message.successTitle }}
        </h2>
        <p class="text-gray-600">{{ message.successDescription }}</p>
      </div>

      <!-- Error state -->
      <div v-else>
        <h2 class="text-lg font-medium text-gray-800 mb-4">
          {{ message.confirmationTitle }}
        </h2>

        <button
          class="w-full py-3 rounded-lg bg-red-600 text-white font-medium hover:bg-red-700 disabled:opacity-50"
          :disabled="isProcessing"
          @click="unsubscribe(keys)"
        >
          <span v-if="!isProcessing">{{ message.button }}</span>
          <span v-else>Processing...</span>
        </button>

        <p v-if="errorMessage" class="mt-4 text-red-600 font-medium">
          {{ errorMessage }}
        </p>
      </div>
    </div>
  </div>
</template>
