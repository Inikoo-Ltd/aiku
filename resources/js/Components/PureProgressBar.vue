<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faTimes } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'

// Tambahkan ikon ke library FontAwesome
library.add(faCheck, faTimes, faSpinnerThird)

const props = defineProps<{
  progressBars: {
    data: {
      number_success: number
      number_fails: number
    }
    done: boolean | number
    total: number
  }
}>()

const progress = computed(() => props.progressBars)
const success = computed(() => progress.value.data.number_success)
const fails = computed(() => progress.value.data.number_fails)
const total = computed(() => progress.value.total)
const finished = computed(() => success.value + fails.value)

const successPercent = computed(() => (success.value / total.value) * 100)
const failPercent = computed(() => (fails.value / total.value) * 100)

const isDone = computed(() =>
  progress.value.done === true || Number(progress.value.done) >= total.value
)

const statusIcon = computed(() => {
  if (!isDone.value) return faSpinnerThird
  return fails.value > 0 ? faTimes : faCheck
})

const statusClass = computed(() => {
  if (!isDone.value) return 'text-gray-400 animate-spin'
  return fails.value > 0 ? 'text-red-500' : 'text-green-600'
})
</script>

<template>
  <div class="w-72 text-sm text-gray-700 space-y-1 py-2 flex gap-2">
    <!-- Progress Bar with Centered Text -->
    <div class="relative h-5 w-full bg-gray-200 rounded overflow-hidden ring-1 ring-gray-300 flex">
      <!-- Success bar -->
      <div
        class="h-full bg-lime-500 transition-all duration-300"
        :style="{ width: successPercent + '%' }"
      />
      <!-- Fail bar -->
      <div
        class="h-full bg-red-500 transition-all duration-300"
        :style="{ width: failPercent + '%' }"
      />

      <!-- Centered Text -->
      <div class="absolute inset-0 flex items-center justify-center text-[11px] text-gray-700 font-medium pointer-events-none z-10">
        <span>
          {{ isDone ? `${finished} / ${total}` : trans("Loading...") + ` (${finished} / ${total})` }}
        </span>
      </div>
    </div>

    <!-- Count Labels with Icons -->
    <div class="flex justify-between text-xs gap-4">
      <span class="flex items-center gap-1 text-lime-600">
        <FontAwesomeIcon :icon="faCheck" class="w-3.5 h-3.5" v-tooltip="'success to upload'"/>
        {{ success }}
      </span>
      <span class="flex items-center gap-1 text-red-500">
        <FontAwesomeIcon :icon="faTimes" class="w-3.5 h-3.5" v-tooltip="'failed to upload'"/>
        {{ fails }}
      </span>
    </div>
  </div>
</template>
