<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 Feb 2023 14:32:57 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"


const props = defineProps<{
    title: string
    pageHead: {}
    tabs: {
        current: string
        navigation: object
    }
    dashboard?: object
}>()

import { library } from '@fortawesome/fontawesome-svg-core'
import { faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faSignOut } from '@fal'
import SimpleBox from '@/Components/DataDisplay/SimpleBox.vue'
import { routeType } from '@/types/route'
import Tabs from '@/Components/Navigation/Tabs.vue'
import DummyComponent from '@/Components/DummyComponent.vue'
import { computed } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import { ref } from 'vue'

library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faSignOut);


let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
const component = computed(() => {
    const components = {
        dashboard: DummyComponent,
    }

    return components[currentTab.value]
})

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <!-- <SimpleBox v-if="box_stats" :box_stats="box_stats" /> -->

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>



</template>
