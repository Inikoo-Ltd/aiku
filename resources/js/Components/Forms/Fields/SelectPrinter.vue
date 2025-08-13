<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown } from '@fas'
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, computed, onMounted } from "vue"
import Dialog from 'primevue/dialog'
import Tag from '@/Components/Tag.vue'

library.add(faChevronDown)

const props = defineProps<{
  form: any
  fieldName: any
  options: { label: string, value: string }[]
  fieldData: {
    placeholder?: string
    required?: boolean
    mode?: "multiple" | "single" | "tags"
    searchable?: boolean
    readonly?: boolean
  }
}>()

const showModal = ref(false)

onMounted(() => {
  if (props.fieldData?.required && !props.form[props.fieldName]) {
    props.form[props.fieldName] = props.fieldData.mode === "multiple" ? [] : props.options?.[0]?.value
  }
})

const openModal = () => {
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const filteredOptions = computed(() => props.fieldData.options)

const selectOption = (val: string) => {
  if (props.fieldData.mode === "multiple" || props.fieldData.mode === "tags") {
    const current = props.form[props.fieldName] || []
    if (current.includes(val)) {
      props.form[props.fieldName] = current.filter(v => v !== val)
    } else {
      props.form[props.fieldName] = [...current, val]
    }
  } else {
    props.form[props.fieldName] = val
    closeModal()
  }
}
</script>

<template>
  <div>
    <!-- Card view -->
    <div
      class="border rounded-lg p-4 flex justify-between items-center cursor-pointer hover:shadow-md transition"
      :class="form.errors[fieldName] ? 'border-red-500' : 'border-gray-300'"
      @click="openModal"
    >
      <div class="flex flex-wrap gap-2">
        <template v-if="Array.isArray(form[fieldName]) && form[fieldName].length">
          <Tag
            v-for="(val, idx) in form[fieldName]"
            :key="idx"
            :label="(props.options.find(o => o.value === val) || {}).label || val"
            :stringToColor="true"
            size="sm"
          />
        </template>
        <template v-else-if="form[fieldName]">
          {{ (props.options.find(o => o.value === form[fieldName]) || {}).label || form[fieldName] }}
        </template>
        <span v-else class="text-gray-400">{{ fieldData.placeholder ?? 'Select an option' }}</span>
      </div>
      <FontAwesomeIcon icon="fas fa-chevron-down" class="text-gray-500" />
    </div>

    <p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-600">
      {{ form.errors[fieldName] }}
    </p>

    <!-- PrimeVue Dialog -->
    <Dialog
      v-model:visible="showModal"
      modal
      header="Select Options"
      :style="{ width: '35rem' }"
    >
      <!-- Options list with scroll -->
      <div class="grid gap-2 overflow-y-auto" style="max-height: 300px;">
        <button
          v-for="option in filteredOptions"
          :key="option.value"
          @click="selectOption(option.value)"
          class="p-3 border rounded-md text-left hover:bg-gray-100 flex justify-between items-center"
          :class="{
            'bg-indigo-50 border-indigo-400': Array.isArray(form[fieldName]) ? form[fieldName].includes(option.value) : form[fieldName] === option.value
          }"
        >
          <span>{{ option.label }}</span>
          <span v-if="Array.isArray(form[fieldName]) ? form[fieldName].includes(option.value) : form[fieldName] === option.value" class="text-indigo-500 font-bold">✔</span>
        </button>
      </div>

      <template #footer>
        <button
          class="px-4 py-2 bg-gray-200 rounded-md hover:bg-gray-300"
          @click="closeModal"
        >
          Close
        </button>
      </template>
    </Dialog>
  </div>
</template>
