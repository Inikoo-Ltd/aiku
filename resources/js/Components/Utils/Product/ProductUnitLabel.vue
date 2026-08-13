<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'

defineProps<{
    units: number | string
    unit?: string
}>()
</script>

<template>
    <span class="inline-flex items-center border border-gray-300 rounded px-2 py-0.5">
        <span class="w-max">{{ Number(units) }}</span>
        <span v-if="unit" v-tooltip="trans('Unit label')" class="ml-0.5 lowercase w-max text-[0.85em] font-light opacity-50">
            ({{ unit }})
        </span>
        <span v-if="unit" class="ml-0.5 lowercase w-max">x</span>
    </span>
</template>
