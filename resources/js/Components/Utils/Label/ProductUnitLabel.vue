<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    units: number | string
    unit?: string
}>()

const ignoredLabel = ['piece', "Piece"]
</script>

<template>
    <span class="inline-flex items-center border border-gray-300 rounded px-2 py-0.5">
        <span>{{ Number(units) }}</span>
        <span v-if="unit" class="ml-0.5 lowercase">x
            <span v-if="!ignoredLabel.includes(unit || 'xxxxxx')" v-tooltip="trans('Unit label')" class="text-[0.85em]">
                <span class="font-light opacity-50">[</span>
                <span class="font-light ">{{ unit }}</span>
                <span class="opacity-50 font-light">]</span>
            </span>
        </span>
    </span>
</template>