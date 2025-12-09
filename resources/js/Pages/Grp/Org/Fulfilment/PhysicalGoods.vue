<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 10:43:31 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TablePhysicalGoods from "@/Components/Tables/Grp/Org/Fulfilment/TablePhysicalGoods.vue";
import {capitalize} from "@/Composables/capitalize"
import {computed, ref} from "vue";
import {useTabChange} from "@/Composables/tab-change";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import type { Navigation } from "@/types/Tabs";
import { PageHeadingTypes } from "@/types/PageHeading";

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  tabs: {
    current: string
    navigation: Navigation
  }
  physical_goods?: {}
  history?: {}
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

  const components = {
    physical_goods: TablePhysicalGoods,
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

