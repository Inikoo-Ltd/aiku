<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableShops from "@/Components/Tables/Grp/Org/Catalogue/TableShops.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"

import TableDepartments from "@/Components/Tables/Grp/Org/Catalogue/TableDepartments.vue"
import TableFamilies from "@/Components/Tables/Grp/Org/Catalogue/TableFamilies.vue"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import { useTabChange } from "@/Composables/tab-change"
import { faCube, faFolder, faFolderTree } from '@fal'
import { PageHeadingTypes } from "@/types/PageHeading"

library.add( faCube, faFolder, faFolderTree )

const props = defineProps<{
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    title: string
    shops?: {}
    departments?: {}
    families?: {}
    products?: {}

}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        shops: TableShops,
        departments: TableDepartments,
        families: TableFamilies,
        products: TableProducts,
    }
    return components[currentTab.value]

})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]" />
</template>
