<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal } from "@fal"
import { computed, ref } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import type { Navigation } from "@/types/Tabs"
import TradeUnitFamiliesShowcase from "@/Components/Goods/TradeUnitFamiliesShowcase.vue"
import { routeType } from "@/types/route"
import ListSelector from "@/Components/ListSelector.vue"
import TableTradeUnits from "@/Components/Tables/Grp/Goods/TableTradeUnits.vue"
import { useTabChange } from "@/Composables/tab-change"

// PrimeVue
import Dialog from "primevue/dialog"

library.add(faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: {
        current: string;
        navigation: Navigation
    }
    routes?: {
        trade_units_route: routeType
        attach_route: routeType
    }
    showcase?: object,
    trade_units?: Object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
const tradeUnit = ref(null)

const isModalOpen = ref(false)

const component = computed(() => {
    const components = {
        showcase: TradeUnitFamiliesShowcase,
        trade_units : TableTradeUnits
    }
    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button @click="isModalOpen = true" label="add Trade unit" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :tag_routes />

    <!-- PrimeVue Dialog -->
    <Dialog v-model:visible="isModalOpen" modal header="Example Modal" :style="{ width: '50vw' }">
        <ListSelector v-model="tradeUnit" :routeFetch="props.routes.trade_units_route" />
    </Dialog>
</template>
