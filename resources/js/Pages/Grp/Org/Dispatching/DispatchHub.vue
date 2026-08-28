<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 Feb 2023 14:32:57 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import { trans } from "laravel-vue-i18n"
import { faHandsHelping, faBan, faCheckCircle, faList, faCheck, faPersonCarry, faChartLine } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import DispatchDashboard from "@/Components/Warehouse/DispatchDashboard.vue"
import Table from "@/Components/Table/Table.vue"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faHandsHelping, faBan, faCheckCircle, faList, faCheck, faPersonCarry, faChartLine)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    delivery_note?: object
    picking_session?: object
    pickers_current?: {}
    packers_current?: {}
    reports_route?: { name: string; parameters: Record<string, string> }
    intervals: any
    settings: any
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const isPersonnelTab = computed(() => currentTab.value === "pickers" || currentTab.value === "packers")
const currentWorkData = computed(() => currentTab.value === "pickers" ? props.pickers_current : props.packers_current)

const orderRoute = (order: { slug: string }) =>
    route("grp.org.warehouses.show.dispatching.delivery_notes.show", {
        organisation: route().params["organisation"],
        warehouse: route().params["warehouse"],
        deliveryNote: order.slug,
    })

const trolleyRoute = (trolley: { slug: string }) =>
    route("grp.org.warehouses.show.dispatching.trolleys.show", {
        organisation: route().params["organisation"],
        warehouse: route().params["warehouse"],
        trolley: trolley.slug,
    })
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <template v-if="isPersonnelTab">
        <div class="px-4 pt-2">
            <div class="flex justify-end">
                <Link
                    v-if="reports_route"
                    :href="route(reports_route.name, reports_route.parameters)"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    {{ trans("Performance reports") }} →
                </Link>
            </div>
            <Table :resource="currentWorkData" :name="currentTab + '_current'">
                <template #cell(orders)="{ item }">
                    <div v-if="item.orders?.length" class="flex flex-wrap gap-1">
                        <Link
                            v-for="order in item.orders"
                            :key="order.slug"
                            :href="orderRoute(order)"
                            class="inline-block px-1.5 py-0.5 rounded text-xs font-mono bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"
                        >
                            {{ order.reference }}
                        </Link>
                    </div>
                    <span v-else class="text-xs text-gray-300 italic">—</span>
                </template>
                <template #cell(trolleys)="{ item }">
                    <div v-if="item.trolleys?.length" class="flex flex-wrap gap-1">
                        <Link
                            v-for="trolley in item.trolleys"
                            :key="trolley.slug"
                            :href="trolleyRoute(trolley)"
                            class="inline-block px-1.5 py-0.5 rounded text-xs font-mono bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors"
                        >
                            {{ trolley.name }}
                        </Link>
                    </div>
                    <span v-else class="text-xs text-gray-300 italic">—</span>
                </template>
            </Table>
        </div>
    </template>

    <component
        v-else
        :is="DispatchDashboard"
        :tab="currentTab"
        :data="currentTab === 'picking_session' ? picking_session : delivery_note"
    ></component>
</template>
