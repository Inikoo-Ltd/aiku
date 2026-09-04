<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:45:56 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TabsBox from "@/Components/Navigation/TabsBox.vue"
import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faBoxOpen } from '@fal'
import { ref } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import TableOrders from '@/Components/Tables/Grp/Org/Ordering/TableOrders.vue'
import TableDeliveryNotes from '@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue'
import { computed } from 'vue'

library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faBoxOpen)

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
    scope_filter?: {
        prefix: string
        current: 'domestic' | 'export' | null
        counts: { domestic: number, export: number }
    }
}>()

const setScope = (scope: 'domestic' | 'export') => {
    const url = new URL(window.location.href)
    const key = `${props.scope_filter?.prefix}_elements[scope]`
    if (props.scope_filter?.current === scope) {
        url.searchParams.delete(key)
    } else {
        url.searchParams.set(key, scope)
    }
    url.searchParams.delete(`${props.scope_filter?.prefix}Page`)
    router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, replace: true })
}

const currentTab = ref(props.tabs.current)
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
        {{ trans("Current backlog") }}
    </div>
    <KeepAlive>
      <TabsBox :tabs_box="tabs.navigation" :current="currentTab" @update:tab="handleTabUpdate" />
    </KeepAlive>
    <div v-if="scope_filter" class="mx-4 mt-3 flex flex-wrap items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900">
        <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans("Destination") }}</span>
        <button
            v-for="scope in (['domestic', 'export'] as const)"
            :key="scope"
            type="button"
            class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
            :class="scope_filter.current === scope
                ? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
                : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
            @click="setScope(scope)">
            <span>{{ scope === 'domestic' ? trans('Domestic') : trans('Export') }}</span>
            <span class="rounded-full px-1.5 text-xs tabular-nums" :class="scope_filter.current === scope ? 'bg-white/20' : 'bg-white text-gray-500'">{{ scope_filter.counts[scope] }}</span>
        </button>
        <button v-if="scope_filter.current" type="button" class="ml-2 text-xs text-gray-400 hover:text-gray-600" @click="setScope(scope_filter.current)">× {{ trans("Clear") }}</button>
    </div>
    <!-- <TableOrders :key="currentTab" :tab="currentTab" :data="props[currentTab]"></TableOrders> -->
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>

</template>
