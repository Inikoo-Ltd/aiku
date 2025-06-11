<script setup lang="ts">
import { ref } from 'vue'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'

const props = defineProps<{
  modelValue: {
    by_url: boolean
    source: string | null
    embed_code: string | null
  } | null
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: {
    by_url: boolean
    source: string | null
    embed_code: string | null
  }): void
}>()

// Local state using ref
const byUrl = ref(props.modelValue?.by_url ?? true)
const source = ref(props.modelValue?.source ?? '')
const embedCode = ref(props.modelValue?.embed_code ?? '')

function emitUpdate() {
  emits('update:modelValue', {
    by_url: byUrl.value,
    source: source.value,
    embed_code: embedCode.value
  })
}

const typeOptions = [
  { label: 'URL Video', value: true },
  { label: 'Embed Code', value: false }
]
</script>

<template>
  <div class="space-y-4">
    <!-- Video Type -->
    <div class="flex flex-col gap-2">
      <label class="font-medium text-sm">Video Source Type</label>
      <Dropdown
        v-model="byUrl"
        :options="typeOptions"
        optionLabel="label"
        optionValue="value"
        class="w-full"
        placeholder="Select video type"
        @change="emitUpdate"
      />
    </div>

    <!-- Video URL -->
    <div v-if="byUrl" class="flex flex-col gap-2">
      <label class="font-medium text-sm">Video URL</label>
      <InputText
        v-model="source"
        class="w-full"
        placeholder="https://example.com/video.mp4"
        @input="emitUpdate"
      />
    </div>

    <!-- Embed Code -->
    <div v-else class="flex flex-col gap-2">
      <label class="font-medium text-sm">Embed HTML</label>
      <Textarea
        v-model="embedCode"
        autoResize
        rows="5"
        class="w-full"
        placeholder="Paste Vimeo or YouTube iframe embed code here"
        @input="emitUpdate"
      />
    </div>
  </div>
</template>

<style scoped>
.space-y-4 > * + * {
  margin-top: 1rem;
}
</style>
