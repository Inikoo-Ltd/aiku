<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheckCircle, faTimesCircle, faFileInvoice, faClock } from '@fal'
import { ctrans } from '@/Composables/useTrans'
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
    <div v-if="channelHealth?.length">
        <div class="mx-4 my-3 overflow-hidden bg-white border border-gray-200 rounded-lg shadow-sm sm:hidden">
            <table class="w-full text-xs tabular-nums">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="w-7" />
                        <th class="py-1 font-normal"><FontAwesomeIcon icon="fal fa-times-circle" class="text-red-400" fixed-width :aria-label="ctrans('Not connected')" /></th>
                        <th class="py-1 font-normal"><FontAwesomeIcon icon="fal fa-check-circle" class="text-green-400" fixed-width :aria-label="ctrans('Connected OK')" /></th>
                        <th class="py-1 font-normal"><FontAwesomeIcon icon="fal fa-file-invoice" class="text-blue-400" fixed-width :aria-label="ctrans('Connected with invoices (all time)')" /></th>
                        <th class="py-1 font-normal"><FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width :aria-label="ctrans('Connected with invoice in last 30 days')" /></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="platform in channelHealth" :key="platform.type" class="text-center font-bold">
                        <td class="py-1 pl-2">
                            <img v-if="platform.logo" :src="platform.logo" :alt="platform.name" class="w-4 h-4 object-contain" />
                        </td>
                        <td class="py-1"><Link :href="buildHref(platform.routes.problem)" class="text-red-600">{{ platform.problem }}</Link></td>
                        <td class="py-1"><Link :href="buildHref(platform.routes.ok)" class="text-green-600">{{ platform.ok }}</Link></td>
                        <td class="py-1"><Link :href="buildHref(platform.routes.ok_with_invoices)" class="text-blue-600">{{ platform.ok_with_invoices }}</Link></td>
                        <td class="py-1"><Link :href="buildHref(platform.routes.ok_with_recent_invoices)" class="text-gray-600">{{ platform.ok_with_recent_invoices }}</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 hidden sm:flex sm:flex-wrap sm:gap-3">
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
                <!-- <span class="font-semibold text-gray-700">{{ platform.name }}</span> -->

                <div class="flex items-center gap-1 border-l border-gray-200 pl-2 ml-1">
                    <FontAwesomeIcon icon="fal fa-times-circle" class="text-red-400 text-xs" fixed-width />
                    <Link
                        v-tooltip="ctrans('Not connected')"
                        :href="buildHref(platform.routes.problem)"
                        class="font-bold text-red-600 tabular-nums hover:underline"
                    >{{ platform.problem }}</Link>
                </div>

                <div class="flex items-center gap-1 border-l border-gray-200 pl-2">
                    <FontAwesomeIcon icon="fal fa-check-circle" class="text-green-400 text-xs" fixed-width />
                    <Link
                        v-tooltip="ctrans('Connected OK')"
                        :href="buildHref(platform.routes.ok)"
                        class="font-bold text-green-600 tabular-nums hover:underline"
                    >{{ platform.ok }}</Link>
                </div>

                <div class="flex items-center gap-1 border-l border-gray-200 pl-2">
                    <FontAwesomeIcon icon="fal fa-file-invoice" class="text-blue-400 text-xs" fixed-width />
                    <Link
                        v-tooltip="ctrans('Connected with invoices (all time)')"
                        :href="buildHref(platform.routes.ok_with_invoices)"
                        class="font-bold text-blue-600 tabular-nums hover:underline"
                    >{{ platform.ok_with_invoices }}</Link>
                </div>

                <div class="flex items-center gap-1 border-l border-gray-200 pl-2">
                    <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400 text-xs" fixed-width />
                    <Link
                        v-tooltip="ctrans('Connected with invoice in last 30 days')"
                        :href="buildHref(platform.routes.ok_with_recent_invoices)"
                        class="font-bold text-gray-600 tabular-nums hover:underline"
                    >{{ platform.ok_with_recent_invoices }}</Link>
                </div>
            </div>
        </div>
    </div>
</template>
