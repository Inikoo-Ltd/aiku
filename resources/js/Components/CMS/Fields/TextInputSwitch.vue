<script setup lang="ts">
import { computed } from "vue"
import InputText from "primevue/inputtext"
import { Switch } from "@headlessui/vue"
import { trans } from "laravel-vue-i18n";

const props = defineProps<{
  modelValue: {
    show: boolean
    text: string
  }
}>()

const emit = defineEmits<{
  (e: "update:modelValue", value: {
    show: boolean
    text: string
  }): void
}>()

const isMostPopular = computed<boolean>({
  get: () => props.modelValue.show,
  set: (v) =>
    emit("update:modelValue", {
      ...props.modelValue,
      show: v
    })
})

const mostPopularText = computed<string>({
  get: () => props.modelValue.text,
  set: (v) =>
    emit("update:modelValue", {
      ...props.modelValue,
      text: v
    })
})
</script>

<template>
  <div class="space-y-2">
    <!-- Switch row with your Tailwind/headlessui classes -->
    <div class="flex items-center justify-between">
      <span class="text-xs">{{ trans("toggle") }}</span>
      <Switch
        v-model="isMostPopular"
        :class="[ isMostPopular ? 'bg-slate-600' : 'bg-slate-300' ]"
        class="pr-1 relative inline-flex h-3 w-6 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75"
      >
        <span
          aria-hidden="true"
          :class="isMostPopular ? 'translate-x-3' : 'translate-x-0'"
          class="pointer-events-none inline-block h-full w-1/2 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
        />
      </Switch>
    </div>

    <!-- Conditionally rendered InputText -->
    <div v-if="isMostPopular" class="pt-1">
      <InputText
        v-model="mostPopularText"
        placeholder="Enter badge text"
        class="w-full"
      />
    </div>
  </div>
</template>
