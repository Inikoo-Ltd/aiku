<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 07 Aug 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'

defineProps<{
    data: {
        period_label: string
        from: string | null
        checks: {
            key: string
            label: string
            status: 'ok' | 'warning' | 'error'
            value: string
            hint: string
            items: string[]
        }[]
    }
}>()

const dotClass = {
    ok: 'bg-green-500',
    warning: 'bg-amber-500',
    error: 'bg-red-500',
}
</script>

<template>
    <div class="px-4 py-4 space-y-3">
        <div class="text-xs text-gray-500">
            {{ trans('Period') }}: {{ data.period_label }}
        </div>

        <div v-for="check in data.checks" :key="check.key"
             class="border border-gray-200 rounded-md px-4 py-3">
            <div class="flex items-center gap-x-2">
                <span class="w-2 h-2 rounded-full shrink-0" :class="dotClass[check.status]" />
                <span class="text-sm">{{ check.label }}</span>
                <span class="ml-auto text-sm tabular-nums">{{ check.value }}</span>
            </div>
            <div class="mt-1 pl-4 text-xs text-gray-500">{{ check.hint }}</div>
            <ul v-if="check.items.length" class="mt-2 pl-4 text-xs text-gray-600 space-y-0.5">
                <li v-for="item in check.items" :key="item">{{ item }}</li>
            </ul>
        </div>
    </div>
</template>
