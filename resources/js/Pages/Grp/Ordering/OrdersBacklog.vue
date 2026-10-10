<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:45:56 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TabsBox from "@/Components/Navigation/TabsBox.vue"
import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faBoxOpen, faAppleCrate } from '@fal'
import { useTabChange, useCurrentTab } from '@/Composables/tab-change'
import TableOrders from '@/Components/Tables/Grp/Org/Ordering/TableOrders.vue'
import TableDeliveryNotes from '@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue'
import { computed } from 'vue'
import { routeType } from '@/types/route'

library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faBoxOpen, faAppleCrate)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    in_basket?: {}
    submitted_paid?: {}
    submitted_unpaid?: {}
    picking?: {}
    blocked?: {}
    packed_done?: {}
    dispatched_today?: {}
    finalise?: {}
    creating?: {}
    submitted?: {}
    in_warehouse?: {}
    handling?: {}
    handling_blocked?: {}
    packed?: {}
    finalised?: {}
    dispatched?: {}
    cancelled?: {}
    picked?: {}
    packing?: {}
    returned?: {}
    backlog_filters?: {
        prefix: string
        current: { scope: 'domestic' | 'export' | null, channel: 'direct' | 'partner' | null, production_review?: 'reviewed' | 'unreviewed' | null }
        counts: {
            scope: { domestic: number, export: number }
            channel: { direct: number, partner: number }
            production_review?: { reviewed: number, unreviewed: number }
        }
    }
    production_review_bulk_route?: routeType | null
}>()

type BacklogFilterKey = 'scope' | 'channel' | 'production_review'

const filterGroups = computed(() => [
    {
        key: 'scope' as BacklogFilterKey,
        label: ctrans('Destination'),
        options: [
            { value: 'domestic', label: ctrans('Domestic') },
            { value: 'export', label: ctrans('Export') },
        ],
    },
    {
        key: 'channel' as BacklogFilterKey,
        label: ctrans('Channel'),
        options: [
            { value: 'direct', label: ctrans('Direct') },
            { value: 'partner', label: ctrans('Partner') },
        ],
    },
    ...(props.backlog_filters?.counts.production_review ? [{
        key: 'production_review' as BacklogFilterKey,
        label: ctrans('Production review'),
        options: [
            { value: 'reviewed', label: ctrans('Production reviewed') },
            { value: 'unreviewed', label: ctrans('Unreviewed') },
        ],
    }] : []),
])

const setFilter = (key: BacklogFilterKey, value: string | null) => {
    const url = new URL(window.location.href)
    const param = `${props.backlog_filters?.prefix}_elements[${key}]`
    if (value === null || props.backlog_filters?.current[key] === value) {
        url.searchParams.delete(param)
    } else {
        url.searchParams.set(param, value)
    }
    url.searchParams.delete(`${props.backlog_filters?.prefix}Page`)
    router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, replace: true })
}

const currentTab = useCurrentTab(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: any = {
      in_basket: TableOrders,
      submitted_paid: TableOrders,
      submitted_unpaid: TableOrders,
      in_warehouse: TableOrders,
      handling: TableOrders,
      handling_blocked: TableOrders,
      picked: TableOrders,
      packing: TableOrders,
      packed: TableOrders,
      finalised: TableOrders,
      dispatched_today: TableOrders,
      returned: TableDeliveryNotes
    }

    return components[currentTab.value]
})

const hasDateFilter = computed(() => /between(%5B|\[)/.test(usePage().url))
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <div v-if="hasDateFilter" class="px-4 pt-2 text-xs text-gray-500">
        {{ ctrans("Current backlog") }}
    </div>
    <KeepAlive>
      <TabsBox :tabs_box="tabs.navigation" :current="currentTab" @update:tab="handleTabUpdate" />
    </KeepAlive>
    <div v-if="backlog_filters" class="mx-4 mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900">
        <div v-for="group in filterGroups" :key="group.key" class="flex flex-wrap items-center gap-1.5">
            <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ group.label }}</span>
            <button
                v-for="option in group.options"
                :key="option.value"
                type="button"
                class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                :class="backlog_filters.current[group.key] === option.value
                    ? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text] shadow-sm'
                    : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                @click="setFilter(group.key, option.value)">
                <span>{{ option.label }}</span>
                <span class="rounded-full px-1.5 text-xs tabular-nums" :class="backlog_filters.current[group.key] === option.value ? 'bg-white/20' : 'bg-white text-gray-500'">{{ backlog_filters.counts[group.key][option.value] }}</span>
            </button>
            <button v-if="backlog_filters.current[group.key]" type="button" class="ml-2 text-xs text-gray-400 hover:text-gray-600" @click="setFilter(group.key, null)">× {{ ctrans("Clear") }}</button>
        </div>
    </div>
    <!-- <TableOrders :key="currentTab" :tab="currentTab" :data="props[currentTab]"></TableOrders> -->
    <component :is="component" :tab="currentTab" :data="props[currentTab]" v-bind="component === TableOrders ? { productionReviewBulkRoute: production_review_bulk_route } : {}"></component>

</template>
