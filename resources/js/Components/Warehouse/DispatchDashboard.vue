<script setup lang="ts">
import { ref, computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faClock,
    faList,
    faCheck,
    faBox,
    faCheckCircle
} from "@fal"

library.add(faClock, faList, faCheck, faBox, faCheckCircle)

const props = defineProps<{
    data: any
}>()

const dashboard = ref({
    dimension: {
        key: "channel",
        label: "Channel",
        items: [
            { key: "fulfilment", label: "Fulfilment" },
            { key: "wholesale", label: "Wholesale" },
        ]
    },

    metrics: [
        {
            key: "todo",
            label: "To do",
            type: "stat",
            icon: ["fal", "fa-clock"]
        },
        {
            key: "warehouse",
            label: "Warehouse",
            type: "group",
            items: [
                { key: "picking", label: "Picking", icon: ["fal", "fa-list"] },
                { key: "packed", label: "Packed", icon: ["fal", "fa-box"] },
                { key: "packing", label: "Packing", icon: ["fal", "fa-check"] }
            ]
        },
        {
            key: "finalised",
            label: "Finalised",
            type: "stat",
            icon: ["fal", "fa-check-circle"]
        }
    ],

    data: {
        fulfilment: {
            todo: {
                value: 2,
                route_target: {
                    "name": "grp.org.warehouses.show.dispatching.pallet-returns.picking.index",
                    "parameters": {
                        "organisation": "sk",
                        "warehouse": "cpt"
                    }
                }
            },
            picking: {
                value: 5, route_target: {
                    "name": "grp.org.warehouses.show.dispatching.pallet-returns.picking.index",
                    "parameters": {
                        "organisation": "sk",
                        "warehouse": "cpt"
                    }
                }
            },
            packed: {
                value: 3, route_target: {
                    "name": "grp.org.warehouses.show.dispatching.pallet-returns.picking.index",
                    "parameters": {
                        "organisation": "sk",
                        "warehouse": "cpt"
                    }
                }
            },
            packing: {
                value: 1, route_target: {
                    "name": "grp.org.warehouses.show.dispatching.pallet-returns.picking.index",
                    "parameters": {
                        "organisation": "sk",
                        "warehouse": "cpt"
                    }
                }
            },
            finalised: {
                value: 8, route_target: {
                    "name": "grp.org.warehouses.show.dispatching.pallet-returns.picking.index",
                    "parameters": {
                        "organisation": "sk",
                        "warehouse": "cpt"
                    }
                }
            }
        },
        wholesale: {
            todo: {
                value: 1, route_target: {
                    "name": "grp.org.warehouses.show.dispatching.pallet-returns.picking.index",
                    "parameters": {
                        "organisation": "sk",
                        "warehouse": "cpt"
                    }
                }
            },
            packing: {
                value: 0, route_target: {
                    "name": "grp.org.warehouses.show.dispatching.pallet-returns.picking.index",
                    "parameters": {
                        "organisation": "sk",
                        "warehouse": "cpt"
                    }
                }
            },
            finalised: {
                value: 4, route_target: {
                    "name": "grp.org.warehouses.show.dispatching.pallet-returns.picking.index",
                    "parameters": {
                        "organisation": "sk",
                        "warehouse": "cpt"
                    }
                }
            }
        },
    },

    row_totals: {
        fulfilment: { value: 19 },
        wholesale: { value: 8 }
    },

    totals: {
        todo: { value: 3 },
        picking: { value: 7 },
        packed: { value: 4 },
        packing: { value: 1 },
        finalised: { value: 12 }
    },

    grand_total: { value: 27, icon: ["fal", "fa-chart-line"] }
})

const activeData = computed(() => dashboard.value)

const rows = computed(() => {
    if (!activeData.value.dimension) {
        return [{ key: "_global", label: null }]
    }
    return activeData.value.dimension.items
})

const getSafeRoute = (routeTarget: any) => {
    if (!routeTarget) return null
    try {
        if (route().has(routeTarget.name)) {
            return route(routeTarget.name, routeTarget.parameters ?? {})
        }
    } catch {
        return null
    }

    return null
}
</script>

<template>
    <div class="overflow-x-auto">
        <div class="flex gap-4 min-w-max m-2">

            <!-- ================= DIMENSION COLUMN ================= -->
            <div v-if="activeData.dimension"
                class="min-w-[200px] bg-white border rounded-xl flex flex-col text-center border-gray-300">
                <div class="h-16 flex items-center justify-center text-xs font-semibold">
                    {{ activeData.dimension.label }}
                </div>

                <div v-for="row in rows" :key="row.key" class="h-12 flex items-center justify-center text-sm">
                    {{ row.label }}
                </div>

                <div class="h-14 flex items-center justify-center font-semibold">
                    Total
                </div>
            </div>

            <!-- ================= METRICS ================= -->
            <template v-for="metric in activeData.metrics" :key="metric.key">

                <!-- SINGLE METRIC -->
                <div v-if="metric.type !== 'group'"
                    class="min-w-[200px] bg-white border border-gray-300 rounded-xl flex flex-col text-center">
                    <div class="h-16 flex flex-col items-center justify-center text-xs font-semibold gap-1">
                        <span>{{ metric.label }}</span>
                        <FontAwesomeIcon v-if="metric.icon" :icon="metric.icon" class="text-lg" />
                    </div>

                    <template v-for="row in rows" :key="row.key">
                        <component
                            :is="getSafeRoute(activeData.data[row.key]?.[metric.key]?.route_target) ? Link : 'div'"
                            :href="getSafeRoute(activeData.data[row.key]?.[metric.key]?.route_target)" :class="[
                                'h-12 flex items-center justify-center text-lg font-medium',
                                getSafeRoute(activeData.data[row.key]?.[metric.key]?.route_target)
                                    ? 'hover:underline cursor-pointer'
                                    : ''
                            ]">
                            {{ activeData.data[row.key]?.[metric.key]?.value ?? '-' }}
                        </component>
                    </template>

                    <div class="h-14 flex items-center justify-center font-semibold">
                        {{ activeData.totals[metric.key]?.value ?? '-' }}
                    </div>
                </div>

                <!-- GROUP METRIC -->
                <div v-else class="bg-white border border-gray-300 rounded-xl flex">
                    <div v-for="item in metric.items" :key="item.key" class="min-w-[180px] flex flex-col text-center">
                        <div class="h-16 flex flex-col items-center justify-center text-xs font-semibold gap-1">
                            <span>{{ item.label }}</span>
                            <FontAwesomeIcon v-if="item.icon" :icon="item.icon" class="text-lg" />
                        </div>

                        <template v-for="row in rows" :key="row.key + '-' + item.key">
                            <component
                                :is="getSafeRoute(activeData.data[row.key]?.[item.key]?.route_target) ? Link : 'div'"
                                :href="getSafeRoute(activeData.data[row.key]?.[item.key]?.route_target)" :class="[
                                    'h-12 flex items-center justify-center text-lg font-medium',
                                    getSafeRoute(activeData.data[row.key]?.[item.key]?.route_target)
                                        ? 'hover:underline cursor-pointer'
                                        : ''
                                ]">
                                {{ activeData.data[row.key]?.[item.key]?.value ?? '-' }}
                            </component>
                        </template>

                        <div class="h-14 flex items-center justify-center font-semibold">
                            {{ activeData.totals[item.key]?.value ?? '-' }}
                        </div>
                    </div>
                </div>
            </template>

            <!-- ================= ROW TOTAL BOX (FROM JSON) ================= -->
            <div class="min-w-[200px] bg-white border border-gray-300 rounded-xl flex flex-col text-center">
                <div class="h-16 flex flex-col items-center justify-center text-xs font-semibold gap-1">
                    <span>Total</span>
                    <FontAwesomeIcon v-if="activeData.grand_total?.icon" :icon="activeData.grand_total?.icon"
                        class="text-lg" />
                </div>

                <div v-for="row in rows" :key="row.key"
                    class="h-12 flex items-center justify-center text-lg font-semibold">
                    {{ activeData.row_totals[row.key]?.value ?? '-' }}
                </div>

                <div class="h-14 flex items-center justify-center font-bold">
                    {{ activeData.grand_total?.value ?? '-' }}
                </div>
            </div>

        </div>
    </div>
</template>