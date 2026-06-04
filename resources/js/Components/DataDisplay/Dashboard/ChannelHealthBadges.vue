<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheckCircle, faTimesCircle, faFileInvoice, faClock } from '@fal'
import { trans } from 'laravel-vue-i18n'

library.add(faCheckCircle, faTimesCircle, faFileInvoice, faClock)

interface ChannelHealth {
    name: string
    type: string
    logo: string | null
    ok: number
    problem: number
    ok_with_invoices: number
    ok_with_recent_invoices: number
}

defineProps<{
    channelHealth: ChannelHealth[]
}>()
</script>

<template>
    <div v-if="channelHealth?.length" class="px-4 py-3 flex flex-wrap gap-3">
        <div
            v-for="platform in channelHealth"
            :key="platform.type"
            class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-sm text-sm"
        >
            <img
                v-if="platform.logo"
                :src="platform.logo"
                :alt="platform.name"
                class="w-5 h-5 object-contain flex-shrink-0"
            />
            <span class="font-semibold text-gray-700">{{ platform.name }}</span>

            <div class="flex items-center gap-1 border-l border-gray-200 pl-2 ml-1">
                <FontAwesomeIcon icon="fal fa-times-circle" class="text-red-400 text-xs" />
                <span
                    v-tooltip="trans('Not connected')"
                    class="font-bold text-red-600 tabular-nums"
                >{{ platform.problem }}</span>
            </div>

            <div class="flex items-center gap-1 border-l border-gray-200 pl-2">
                <FontAwesomeIcon icon="fal fa-check-circle" class="text-green-400 text-xs" />
                <span
                    v-tooltip="trans('Connected OK')"
                    class="font-bold text-green-600 tabular-nums"
                >{{ platform.ok }}</span>
            </div>

            <div class="flex items-center gap-1 border-l border-gray-200 pl-2">
                <FontAwesomeIcon icon="fal fa-file-invoice" class="text-blue-400 text-xs" />
                <span
                    v-tooltip="trans('Connected with invoices (all time)')"
                    class="font-bold text-blue-600 tabular-nums"
                >{{ platform.ok_with_invoices }}</span>
            </div>

            <div class="flex items-center gap-1 border-l border-gray-200 pl-2">
                <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400 text-xs" />
                <span
                    v-tooltip="trans('Connected with invoice in last 30 days')"
                    class="font-bold text-gray-600 tabular-nums"
                >{{ platform.ok_with_recent_invoices }}</span>
            </div>
        </div>
    </div>
</template>
