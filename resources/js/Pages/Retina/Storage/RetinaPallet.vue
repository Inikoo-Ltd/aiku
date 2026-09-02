<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import type { Component } from "vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { useTabChange } from "@/Composables/tab-change"
import RetinaPalletShowcase from "./RetinaPalletShowcase.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import StockItemsMovements from "@/Components/Showcases/Grp/StockItemsMovements.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExchange, faFragile, faNarwhal, faGhost } from "@fal"
import TableStoredItemsInWarehouse from "@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsInWarehouse.vue"
import Tag from "@/Components/Tag.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"

library.add(faFragile, faNarwhal, faExchange, faGhost)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    is_virtual?: boolean
    stored_items?: {}
    movements?: {}
    history?: {}
    showcase?: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component = computed(() => {
    const components: Component = {
        showcase: RetinaPalletShowcase,
        stored_items: TableStoredItemsInWarehouse,
        movements: StockItemsMovements,
        history: TableHistories
    }
    return components[currentTab.value]

})

console.log("plm", props)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <Tag v-if="is_virtual" :theme="11" size="sm" noHoverColor :closeButton="false"
                v-tooltip="trans('Goods stored on the floor, only their SKOs can be returned')">
                <template #label>
                    <div class="whitespace-nowrap">
                        <FontAwesomeIcon icon="fal fa-ghost" fixed-width aria-hidden="true" />
                        {{ trans("Virtual pallet") }}
                    </div>
                </template>
            </Tag>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
  