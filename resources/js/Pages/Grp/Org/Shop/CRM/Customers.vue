<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup  lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableCustomers from '@/Components/Tables/Grp/Org/CRM/TableCustomers.vue';
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { computed, ref } from "vue"
import CustomerDashboard from '@/Pages/Grp/Dashboard/CustomerDashboard.vue';
import { faCircleNotch } from "@fal"
import { faExclamationCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCircleNotch, faExclamationCircle)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    },
    data: {},
    dashboard?: {}
    customers?: {}
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: any = {
        customers: TableCustomers,
        dashboard: CustomerDashboard
    }

    return components[currentTab.value]
})

</script>

<template>
  <!-- <Head :title="capitalize(title)"/> -->
  <PageHeading :data="pageHead"/>
  <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
  <component :is="component" :key="currentTab" :tab="currentTab" :data="props[currentTab]"></component>
</template>


