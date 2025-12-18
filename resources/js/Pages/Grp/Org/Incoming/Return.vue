<!--
  - Author: Oggie Sutrisna
  - Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
  - Description: Vue page for displaying a single Return
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faUndoAlt, faClock, faInboxIn, faSearch, faCheckDouble, faTimes, faUser, faBox, faCalendar } from '@fal'
import { computed } from 'vue'
import { routeType } from '@/types/route'

library.add(faUndoAlt, faClock, faInboxIn, faSearch, faCheckDouble, faTimes, faUser, faBox, faCalendar)

interface BoxStats {
    state: string
    state_icon: {
        icon: string
        class: string
        tooltip: string
    }
    state_label: string
    customer: {
        name: string
        route?: routeType
    }
    order: {
        reference: string
        route: routeType
    } | null
    items: {
        total: number
        pending: number
        received: number
        accepted: number
        rejected: number
    }
    dates: {
        created: string
        received: string | null
        processed: string | null
    }
}

const props = defineProps<{
    title: string
    pageHead: {}
    return: {}
    box_stats: BoxStats
}>()

const stateColorClass = computed(() => {
    const stateColors: Record<string, string> = {
        waiting_to_receive: 'bg-gray-100 text-gray-800',
        received: 'bg-blue-100 text-blue-800',
        inspecting: 'bg-yellow-100 text-yellow-800',
        processed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    }
    return stateColors[props.box_stats?.state] || 'bg-gray-100 text-gray-800'
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 sm:px-6 lg:px-8 py-6">
        <!-- Status Banner -->
        <div class="mb-6 bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div :class="[stateColorClass, 'px-3 py-1 rounded-full flex items-center gap-2']">
                        <FontAwesomeIcon v-if="box_stats?.state_icon" :icon="box_stats.state_icon.icon" :class="box_stats.state_icon.class" />
                        <span class="font-medium">{{ box_stats?.state_label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Customer Info -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center gap-2 text-gray-500 mb-2">
                    <FontAwesomeIcon icon="fal fa-user" />
                    <span class="text-sm font-medium">Customer</span>
                </div>
                <Link v-if="box_stats?.customer?.route"
                      :href="route(box_stats.customer.route.name, box_stats.customer.route.parameters)"
                      class="text-lg font-semibold text-indigo-600 hover:text-indigo-800">
                    {{ box_stats?.customer?.name }}
                </Link>
                <span v-else class="text-lg font-semibold">{{ box_stats?.customer?.name }}</span>
            </div>

            <!-- Order Info -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center gap-2 text-gray-500 mb-2">
                    <FontAwesomeIcon icon="fal fa-box" />
                    <span class="text-sm font-medium">Original Order</span>
                </div>
                <Link v-if="box_stats?.order?.route"
                      :href="route(box_stats.order.route.name, box_stats.order.route.parameters)"
                      class="text-lg font-semibold text-indigo-600 hover:text-indigo-800">
                    {{ box_stats?.order?.reference }}
                </Link>
                <span v-else class="text-lg font-semibold text-gray-400">-</span>
            </div>

            <!-- Items Summary -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center gap-2 text-gray-500 mb-2">
                    <FontAwesomeIcon icon="fal fa-undo-alt" />
                    <span class="text-sm font-medium">Items</span>
                </div>
                <div class="text-lg font-semibold">{{ box_stats?.items?.total }} items</div>
                <div class="text-sm text-gray-500 flex gap-2 mt-1">
                    <span v-if="box_stats?.items?.pending" class="text-gray-600">{{ box_stats.items.pending }} pending</span>
                    <span v-if="box_stats?.items?.received" class="text-blue-600">{{ box_stats.items.received }} received</span>
                    <span v-if="box_stats?.items?.accepted" class="text-green-600">{{ box_stats.items.accepted }} accepted</span>
                    <span v-if="box_stats?.items?.rejected" class="text-red-600">{{ box_stats.items.rejected }} rejected</span>
                </div>
            </div>
        </div>

        <!-- Return Items Table Placeholder -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-5 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Return Items</h3>
            </div>
            <div class="px-4 py-5 text-center text-gray-500">
                Return items will be displayed here
            </div>
        </div>
    </div>
</template>
