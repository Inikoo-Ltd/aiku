<script setup lang="ts">
import { ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHourglassStart, faHourglassHalf, faChevronDown, faChevronRight } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useLayoutStore } from '@/Stores/layout'
library.add(faHourglassStart, faHourglassHalf, faChevronDown, faChevronRight)

type WarehouseEntry = {
    slug: string
    name: string
    code: string
    waiting_items: { count: number; route: { name: string; parameters: Record<string, string> } }
    waiting_items_still_picking: { count: number; route: { name: string; parameters: Record<string, string> } }
}

type OrgEntry = {
    organisation: { slug: string; name: string; code: string }
    warehouses: WarehouseEntry[]
}

const props = defineProps<{
    open: boolean
    close: () => void
}>()

const data = ref<OrgEntry[]>([])
const isLoading = ref(false)
const expandedOrgs = ref<Set<string>>(new Set())

watch(() => props.open, async (isOpen) => {
    if (!isOpen) {
        return
    }

    isLoading.value = true
    try {
        const response = await axios.get(route('grp.json.dispatching_waiting_badge'))
        data.value = response.data
        data.value.forEach((org: OrgEntry) => expandedOrgs.value.add(org.organisation.slug))

        const layout = useLayoutStore()
        layout.dispatching_waiting_count = data.value.reduce((total, org) =>
            total + org.warehouses.reduce((wTotal, wh) =>
                wTotal + wh.waiting_items.count + wh.waiting_items_still_picking.count, 0
            ), 0
        )
    } finally {
        isLoading.value = false
    }
}, { immediate: true })

function toggleOrg(orgSlug: string): void {
    if (expandedOrgs.value.has(orgSlug)) {
        expandedOrgs.value.delete(orgSlug)
    } else {
        expandedOrgs.value.add(orgSlug)
    }
}
</script>

<template>
    <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
            {{ trans('Warehouse Waiting Items') }}
        </p>

        <div v-if="isLoading" class="space-y-2">
            <div v-for="i in 2" :key="i" class="animate-pulse">
                <div class="h-8 bg-gray-100 rounded mb-1" />
                <div class="pl-3 space-y-1">
                    <div class="h-6 bg-gray-50 rounded w-3/4" />
                    <div class="h-6 bg-gray-50 rounded w-2/3" />
                </div>
            </div>
        </div>

        <div v-else>
            <div v-for="orgData in data" :key="orgData.organisation.slug" class="mb-2">
                <button
                    @click="toggleOrg(orgData.organisation.slug)"
                    class="w-full flex items-center justify-between px-2 py-1.5 rounded hover:bg-gray-50 text-left"
                >
                    <span class="text-sm font-medium text-gray-700">
                        {{ orgData.organisation.name }}
                        <span class="ml-1 text-xs text-gray-400">({{ orgData.organisation.code }})</span>
                    </span>
                    <FontAwesomeIcon
                        :icon="expandedOrgs.has(orgData.organisation.slug) ? 'fal fa-chevron-down' : 'fal fa-chevron-right'"
                        class="text-gray-400 text-xs"
                    />
                </button>

                <div v-if="expandedOrgs.has(orgData.organisation.slug)" class="pl-3 mt-1 space-y-1">
                    <div v-for="warehouse in orgData.warehouses" :key="warehouse.slug" class="space-y-1">
                        <p class="text-xs text-gray-400 font-medium">{{ warehouse.name }}</p>

                        <Link
                            v-if="warehouse.waiting_items.count > 0"
                            :href="route(warehouse.waiting_items.route.name, warehouse.waiting_items.route.parameters)"
                            @click="close()"
                            class="flex items-center justify-between px-2 py-1 rounded hover:bg-orange-50 group"
                        >
                            <div class="flex items-center gap-x-1.5 text-xs text-gray-600 group-hover:text-orange-700">
                                <FontAwesomeIcon icon="fal fa-hourglass-start" class="text-orange-400" fixed-width />
                                <span>{{ trans('Waiting Items') }}</span>
                            </div>
                            <span class="text-xs font-semibold text-orange-600 bg-orange-100 rounded-full px-1.5 py-0.5">
                                {{ warehouse.waiting_items.count }}
                            </span>
                        </Link>

                        <Link
                            v-if="warehouse.waiting_items_still_picking.count > 0"
                            :href="route(warehouse.waiting_items_still_picking.route.name, warehouse.waiting_items_still_picking.route.parameters)"
                            @click="close()"
                            class="flex items-center justify-between px-2 py-1 rounded hover:bg-yellow-50 group"
                        >
                            <div class="flex items-center gap-x-1.5 text-xs text-gray-600 group-hover:text-yellow-700">
                                <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-yellow-500" fixed-width />
                                <span>{{ trans('Still Picking') }}</span>
                            </div>
                            <span class="text-xs font-semibold text-yellow-700 bg-yellow-100 rounded-full px-1.5 py-0.5">
                                {{ warehouse.waiting_items_still_picking.count }}
                            </span>
                        </Link>

                        <div
                            v-if="warehouse.waiting_items.count === 0 && warehouse.waiting_items_still_picking.count === 0"
                            class="px-2 py-1 text-xs text-gray-400 italic"
                        >
                            {{ trans('No waiting items') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
