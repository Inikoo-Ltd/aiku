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
        capture: {
            arrivals: number
            identified_pct: number | null
            rows: {
                visitor: string
                arrivals: number
                matched: number
                repeat: number
                direct: number
                unmatched: number
                internal: number
                identified: string
            }[]
            rejected: { host: string, hits: number }[]
        }
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

        <!-- Capture health: whether visitors arrive with a source we can name at all -->
        <div v-if="data.capture" class="border border-gray-200 rounded-md px-4 py-3">
            <div class="flex items-center gap-x-2">
                <span class="w-2 h-2 rounded-full shrink-0"
                      :class="data.capture.arrivals === 0 ? 'bg-red-500' : 'bg-green-500'" />
                <span class="text-sm">{{ trans('Traffic capture today') }}</span>
                <span class="ml-auto text-sm tabular-nums">
                    {{ data.capture.arrivals }} {{ trans('arrivals') }}
                    <span v-if="data.capture.identified_pct !== null" class="text-gray-500">
                        · {{ data.capture.identified_pct }}% {{ trans('identified') }}
                    </span>
                </span>
            </div>
            <div class="mt-1 pl-4 text-xs text-gray-500">
                {{ trans('Counted across all shops. Arrivals are people landing on a storefront; page views inside the site are counted apart as browsing. No arrivals at all means the storefront is not reporting visits and nothing can be attributed.') }}
            </div>

            <table class="mt-2 ml-4 text-xs text-gray-600">
                <thead>
                    <tr class="text-gray-400">
                        <th class="text-left font-normal pr-4">{{ trans('Visitor') }}</th>
                        <th class="text-right font-normal px-2">{{ trans('Arrivals') }}</th>
                        <th class="text-right font-normal px-2">{{ trans('Source found') }}</th>
                        <th class="text-right font-normal px-2">{{ trans('Came direct') }}</th>
                        <th class="text-right font-normal px-2">{{ trans('Browsing') }}</th>
                        <th class="text-right font-normal pl-2">{{ trans('Identified') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in data.capture.rows" :key="row.visitor">
                        <td class="pr-4 py-0.5">{{ row.visitor }}</td>
                        <td class="text-right px-2 tabular-nums">{{ row.arrivals }}</td>
                        <td class="text-right px-2 tabular-nums">{{ row.matched + row.repeat }}</td>
                        <td class="text-right px-2 tabular-nums">{{ row.direct }}</td>
                        <td class="text-right px-2 tabular-nums">{{ row.internal }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ row.identified }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="data.capture.rejected.length" class="mt-3 ml-4">
                <div class="text-xs text-gray-500">
                    {{ trans('Referrers we did not record: our own systems, and anything malformed.') }}
                </div>
                <ul class="mt-1 text-xs text-gray-600 space-y-0.5">
                    <li v-for="host in data.capture.rejected" :key="host.host">
                        {{ host.host }} <span class="text-gray-400">· {{ host.hits }}</span>
                    </li>
                </ul>
            </div>
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
