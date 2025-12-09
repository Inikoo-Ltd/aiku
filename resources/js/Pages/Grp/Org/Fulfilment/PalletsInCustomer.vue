<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 23 Jul 2024 01:35:25 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import TablePallets from "@/Components/Tables/Grp/Org/Fulfilment/TablePallets.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { type Component, computed, ref } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowAltFromLeft, faAlignJustify, faWarehouseAlt, faSeedling, faSadCry } from "@fal"
import { Table } from "@/types/Table"


library.add(faArrowAltFromLeft, faAlignJustify, faWarehouseAlt, faSeedling, faSadCry)


const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    
    storing: Table
    incoming: Table
    returned: Table
    incident: Table
    all: Table

}>()


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        storing: TablePallets,
        incoming: TablePallets,
        returned: TablePallets,
        incident: TablePallets,
        all: TablePallets
    }

    return components[currentTab.value]
});


</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :key="currentTab" :tab="currentTab" :data="props[currentTab as keyof typeof props]"></component>
</template>
