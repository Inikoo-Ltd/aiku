<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link } from '@inertiajs/vue3'
import Skeleton from 'primevue/skeleton'
import { computed, ref } from 'vue'

type LocationResult = {
    id: number
    code: string
    status: string
    slug: string
}

type WarehouseAreaResult = {
    id: number
    code: string
    name: string
    slug: string
}

type LocationsResults = {
    locations: LocationResult[]
    warehouse_areas: WarehouseAreaResult[]
}

const model = defineModel('open')

const props = defineProps<{
    results: LocationsResults | null
    isLoading: boolean
    query: string
}>()

type Tab = 'locations' | 'warehouse_areas'

const LOCATION_ROUTE = 'grp.org.warehouses.show.infrastructure.locations.show'
const WAREHOUSE_AREA_ROUTE = 'grp.org.warehouses.show.infrastructure.warehouse_areas.show'

const activeTab = ref<Tab>('locations')
const loadingId = ref<number | null>(null)

function buildHref(routeName: string, paramKey: 'location' | 'warehouseArea', slug: string): string {
    const params = (route() as any).routeParams || {}
    const organisation = params.organisation
    const warehouse = params.warehouse

    if (!organisation || !warehouse || !slug) {
        return '#'
    }

    return route(routeName, { organisation, warehouse, [paramKey]: slug })
}

const locationItems = computed(() =>
    (props.results?.locations ?? []).map((location) => ({
        ...location,
        href: buildHref(LOCATION_ROUTE, 'location', location.slug),
    }))
)

const warehouseAreaItems = computed(() =>
    (props.results?.warehouse_areas ?? []).map((area) => ({
        ...area,
        href: buildHref(WAREHOUSE_AREA_ROUTE, 'warehouseArea', area.slug),
    }))
)
</script>

<template>
    <div class="col-span-3 border-r p-4 bg-gray-50">
        <p class="text-xs text-gray-400 mb-1">{{ ctrans("Query") }}</p>
        <p class="font-semibold text-sm mb-4">{{ query }}</p>
        <p class="text-xs text-gray-400 mb-2">{{ ctrans("Summary") }}</p>
        <div v-if="isLoading" class="space-y-2">
            <Skeleton height="2.5rem" borderRadius="0.375rem" />
            <Skeleton height="2.5rem" borderRadius="0.375rem" />
        </div>
        <div v-else class="space-y-2">
            <button
                type="button"
                class="w-full p-3 rounded-md text-sm flex items-center justify-between transition active:scale-[0.98]"
                :class="activeTab === 'locations'
                    ? 'bg-white shadow-sm ring-1 ring-slate-200 text-slate-900'
                    : 'bg-white/60 text-slate-600 hover:bg-slate-100'"
                @click="activeTab = 'locations'"
            >
                <span class="font-medium">
                    <FontAwesomeIcon icon='fal fa-inventory' fixed-width aria-hidden='true' />
                    {{ ctrans("Locations") }}
                </span>
                <span class="text-xs text-gray-400">{{ results?.locations?.length ?? 0 }}</span>
            </button>
            <button
                type="button"
                class="w-full p-3 rounded-md text-sm flex items-center justify-between transition active:scale-[0.98]"
                :class="activeTab === 'warehouse_areas'
                    ? 'bg-white shadow-sm ring-1 ring-slate-200 text-slate-900'
                    : 'bg-white/60 text-slate-600 hover:bg-slate-100'"
                @click="activeTab = 'warehouse_areas'"
            >
                <span class="font-medium">
                    <FontAwesomeIcon icon='fal fa-map-signs' fixed-width aria-hidden='true' />
                    {{ ctrans("Warehouse Areas") }}
                </span>
                <span class="text-xs text-gray-400">{{ results?.warehouse_areas?.length ?? 0 }}</span>
            </button>
        </div>
    </div>

    <div class="col-span-9 flex flex-col min-h-0">
        <div class="flex-1 p-4 space-y-4 overflow-y-auto">
            <div v-if="isLoading" class="space-y-4">
                <div v-for="i in 6" :key="i" class="p-4 rounded-md border bg-white">
                    <div class="flex justify-between items-center mb-2">
                        <Skeleton width="60%" height="1rem" />
                        <Skeleton width="60px" height="0.75rem" borderRadius="999px" />
                    </div>
                    <Skeleton width="40%" height="0.75rem" />
                </div>
            </div>

            <template v-else-if="activeTab === 'locations'">
                <div v-if="locationItems.length">
                    <component
                        :is="location.href === '#' ? 'div' : Link"
                        v-for="location in locationItems"
                        :key="location.id"
                        :href="location.href === '#' ? undefined : location.href"
                        class="block group p-4 rounded-md border border-transparent bg-slate-50 hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm mb-3"
                        :class="location.href === '#' ? 'cursor-default opacity-70' : 'cursor-pointer'"
                        @start="() => { model = false; loadingId = location.id }"
                        @finish="() => loadingId = null"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold truncate min-w-0">{{ location.code }}</p>
                            <span
                                v-if="loadingId === location.id"
                                class="shrink-0 text-slate-400"
                            >
                                <FontAwesomeIcon icon='fal fa-spinner-third' spin fixed-width aria-hidden='true' />
                            </span>
                            <span
                                v-else
                                class="shrink-0 text-[10px] px-2 py-0.5 rounded-full capitalize"
                                :class="location.status === 'active' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ location.status }}
                            </span>
                        </div>
                    </component>
                </div>
                <div v-else class="flex h-full items-center justify-center text-gray-400 text-sm">
                    {{ ctrans("No locations") }}
                </div>
            </template>

            <template v-else>
                <div v-if="warehouseAreaItems.length">
                    <component
                        :is="area.href === '#' ? 'div' : Link"
                        v-for="area in warehouseAreaItems"
                        :key="area.id"
                        :href="area.href === '#' ? undefined : area.href"
                        class="block group p-4 rounded-md border border-transparent bg-slate-50 hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm mb-3"
                        :class="area.href === '#' ? 'cursor-default opacity-70' : 'cursor-pointer'"
                        @start="() => { model = false; loadingId = area.id }"
                        @finish="() => loadingId = null"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold truncate min-w-0">{{ area.name }}</p>
                            <span v-if="loadingId === area.id" class="shrink-0 text-slate-400">
                                <FontAwesomeIcon icon='fal fa-spinner-third' spin fixed-width aria-hidden='true' />
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-2 truncate">{{ ctrans("Code") }}: {{ area.code }}</p>
                    </component>
                </div>
                <div v-else class="flex h-full items-center justify-center text-gray-400 text-sm">
                    {{ ctrans("No warehouse areas") }}
                </div>
            </template>
        </div>
    </div>
</template>
