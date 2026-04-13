<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:45:56 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TabsBox from "@/Components/Navigation/TabsBox.vue"
import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faBoxOpen } from '@fal'
import { ref, computed } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import TableOrders from '@/Components/Tables/Grp/Org/Ordering/TableOrders.vue'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationTriangle } from '@fas'

library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faBoxOpen, faExclamationTriangle)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    waiting_items: {
        count: number
    }
    in_basket: {}
    submitted_paid: {}
    submitted_unpaid: {}
    picking: {}
    blocked: {}
    packed_done: {}
    dispatched_today: {}
    finalise: {}
    creating: {}
    submitted: {}
    in_warehouse: {}
    handling: {}
    handling_blocked: {}
    packed: {}
    finalised: {}
    dispatched: {}
    cancelled: {}
    picked: {}
    packing: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const waitingItemsUrl = (): string | null => {
    try {
        const params = route().params as Record<string, string>
        if (params.organisation && params.shop) {
            return route('grp.org.shops.show.ordering.backlog.waiting_items', {
                organisation: params.organisation,
                shop: params.shop,
            })
        }
    } catch {
        return null
    }
    return null
}

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <!-- Section: Waiting Items for CRM -->
    <div v-if="props.waiting_items.count > 0" class="mx-4 bg-yellow-300 border border-yellow-500 px-4 py-2 rounded-md text-gray-800 mb-4 mt-4">
        <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-yellow-700" fixed-width aria-hidden="true" />
        {{ ctrans('You have :waitingItemsCount items that still waiting for CRM..', { waitingItemsCount: props.waiting_items.count }) }}
        <Link v-if="waitingItemsUrl()" :href="waitingItemsUrl()" class="underline cursor-pointer opacity-80 hover:opacity-100">
            {{ ctrans("Click here to see") }}
        </Link>
        <span v-else class="underline">
            {{ ctrans("Click here to see") }}
        </span>
    </div>

    <KeepAlive>
      <TabsBox :tabs_box="tabs.navigation" :current="currentTab" @update:tab="handleTabUpdate" />
    </KeepAlive>
    <TableOrders :key="currentTab" :tab="currentTab" :data="props[currentTab]"></TableOrders>

</template>
