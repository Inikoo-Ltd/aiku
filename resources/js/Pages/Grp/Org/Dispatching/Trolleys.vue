<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 18:41:25 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { faBars, faWarehouse } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableTrolleys from "@/Components/Tables/Grp/Org/Dispatching/TableTrolleys.vue"


library.add(faBars, faWarehouse)

const props = defineProps<{
    pageHead: {}
    tabs: {
        current: string
        navigation: {}
    },
    title: string
    trolleys?: {}
	trolleys_histories: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
		trolleys: TableTrolleys,
		trolleys_histories: TableHistories

    }
    return components[currentTab.value]

});

</script>

<template>
<!-- <pre>{{ warehouses_histories }}</pre> -->
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
</template>
