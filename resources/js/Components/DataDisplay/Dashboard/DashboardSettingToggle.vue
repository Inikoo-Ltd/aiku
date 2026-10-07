<script setup lang="ts">
import ToggleSwitch from "primevue/toggleswitch"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCheck, faTimes)

defineProps<{
    label: string
    isOn: boolean
    isLoading?: boolean
    tooltip?: string
}>()

const emit = defineEmits<{
    (e: "change", isOn: boolean): void
}>()
</script>

<template>
    <div v-tooltip="tooltip" class="flex items-center gap-x-2 flex-shrink-0 rounded border border-gray-300 px-3 h-9">
        <ToggleSwitch
            class="setting-toggle"
            :modelValue="isOn"
            @update:modelValue="(value: boolean) => emit('change', value)"
            :disabled="isLoading"
        />
        <p class="whitespace-nowrap" :class="isOn ? 'text-gray-700' : 'text-gray-400'">{{ label }}</p>
        <LoadingIcon v-if="isLoading" class="text-gray-400" />
        <FontAwesomeIcon v-else-if="isOn" icon="far fa-check" class="text-green-600" fixed-width aria-hidden="true" />
        <FontAwesomeIcon v-else icon="far fa-times" class="text-gray-400" fixed-width aria-hidden="true" />
    </div>
</template>

<style scoped>
.setting-toggle {
    --p-toggleswitch-checked-background: #16a34a;
    --p-toggleswitch-checked-hover-background: #15803d;
}

.setting-toggle:not(.p-toggleswitch-checked) :deep(.p-toggleswitch-slider) {
    background: #d1d5db;
}
</style>
