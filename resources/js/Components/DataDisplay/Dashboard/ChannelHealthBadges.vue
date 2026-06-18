<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheckCircle, faTimesCircle, faFileInvoice, faClock } from '@fal'
import { trans } from 'laravel-vue-i18n'
import { Link } from '@inertiajs/vue3'

library.add(faCheckCircle, faTimesCircle, faFileInvoice, faClock)

interface RouteTarget {
    name: string
    parameters: Record<string, string>
}

interface ChannelHealth {
    name: string
    type: string
    logo: string | null
    ok: number
    problem: number
    ok_with_invoices: number
    ok_with_recent_invoices: number
    routes: {
        problem: RouteTarget
        ok: RouteTarget
        ok_with_invoices: RouteTarget
        ok_with_recent_invoices: RouteTarget
    }
}

defineProps<{
    channelHealth: ChannelHealth[]
}>()

function buildHref(routeTarget: RouteTarget): string {
    return route(routeTarget.name, routeTarget.parameters)
}
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
                <Link
                    v-tooltip="trans('Not connected')"
                    :href="buildHref(platform.routes.problem)"
                    class="font-bold text-red-600 tabular-nums hover:underline"
                >{{ platform.problem }}</Link>
            </div>

            <div class="flex items-center gap-1 border-l border-gray-200 pl-2">
                <FontAwesomeIcon icon="fal fa-check-circle" class="text-green-400 text-xs" />
                <Link
                    v-tooltip="trans('Connected OK')"
                    :href="buildHref(platform.routes.ok)"
                    class="font-bold text-green-600 tabular-nums hover:underline"
                >{{ platform.ok }}</Link>
            </div>

            <div class="flex items-center gap-1 border-l border-gray-200 pl-2">
                <FontAwesomeIcon icon="fal fa-file-invoice" class="text-blue-400 text-xs" />
                <Link
                    v-tooltip="trans('Connected with invoices (all time)')"
                    :href="buildHref(platform.routes.ok_with_invoices)"
                    class="font-bold text-blue-600 tabular-nums hover:underline"
                >{{ platform.ok_with_invoices }}</Link>
            </div>

            <div class="flex items-center gap-1 border-l border-gray-200 pl-2">
                <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400 text-xs" />
                <Link
                    v-tooltip="trans('Connected with invoice in last 30 days')"
                    :href="buildHref(platform.routes.ok_with_recent_invoices)"
                    class="font-bold text-gray-600 tabular-nums hover:underline"
                >{{ platform.ok_with_recent_invoices }}</Link>
            </div>
        </div>
    </div>
</template>
