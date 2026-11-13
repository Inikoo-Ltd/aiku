<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faFileSignature, faFileExclamation, faFileTimes } from '@fal'

library.add(faFileSignature, faFileExclamation, faFileTimes)

defineProps<{
    status: { status: string, label: string, colour: string, icon: string }
}>()

const colourClass: Record<string, string> = {
    green: 'border-green-200 bg-green-50 text-green-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    red: 'border-red-200 bg-red-50 text-red-700',
}
</script>

<template>
    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-medium" :class="colourClass[status.colour]">
        <FontAwesomeIcon :icon="status.icon" fixed-width aria-hidden="true" />
        {{ status.label }}
    </span>
</template>
