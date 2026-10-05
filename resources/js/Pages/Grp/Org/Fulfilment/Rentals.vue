<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 25 Apr 2024 15:00:59 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableRentals from "@/Components/Tables/Grp/Org/Fulfilment/TableRentals.vue";
import {capitalize} from "@/Composables/capitalize"
import {computed, ref} from "vue";
import {useTabChange} from "@/Composables/tab-change";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGarage, faBars } from "@fal"
library.add(faGarage, faBars)

const props = defineProps<{
  pageHead: object
  title: string
  tabs: {
    current: string
    navigation: {}
  }
  rentals?: {}
  history?: {}
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

  const components = {
    rentals: TableRentals,
    history: TableHistories
  }
  return components[currentTab.value]

});

</script>

<template>
  <Head :title="capitalize(title)"/>
  <PageHeading :data="pageHead"></PageHeading>
  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
  <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
</template>

