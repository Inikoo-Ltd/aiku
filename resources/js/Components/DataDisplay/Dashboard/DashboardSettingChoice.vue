<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCheck)

defineProps<{
    value: string
    options: { value: string, label: string, tooltip?: string }[]
}>()

const emit = defineEmits<{
    (e: "change", value: string): void
}>()
</script>

<template>
    <div class="flex items-stretch flex-shrink-0 rounded border border-gray-300 h-9 overflow-hidden">
        <button
            v-for="(option, idx) in options"
            :key="option.value"
            type="button"
            v-tooltip="option.tooltip"
            @click="option.value !== value && emit('change', option.value)"
            class="flex items-center gap-x-1.5 px-3 whitespace-nowrap focus:outline-none"
            :class="[
                idx > 0 ? 'border-l border-gray-300' : '',
                option.value === value ? 'text-gray-700' : 'text-gray-400 hover:text-gray-600',
            ]"
        >
            <FontAwesomeIcon v-if="option.value === value && idx === 0" icon="far fa-check" class="text-green-600" fixed-width aria-hidden="true" />
            {{ option.label }}
            <FontAwesomeIcon v-if="option.value === value && idx > 0" icon="far fa-check" class="text-green-600" fixed-width aria-hidden="true" />
        </button>
    </div>
</template>
