<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableInvoices from "@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue";
import { capitalize } from "@/Composables/capitalize"
import {library} from '@fortawesome/fontawesome-svg-core';
import Tabs from '@/Components/Navigation/Tabs.vue'
import { computed, ref } from 'vue'
import {
  faFileMinus,
  faArrowCircleLeft
} from "@fal";
import { useTabChange } from '@/Composables/tab-change'
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import TableRefunds from '@/Components/Tables/Grp/Org/Accounting/TableRefunds.vue'

library.add(faFileMinus, faArrowCircleLeft);

const props = defineProps<{
  pageHead: PageHeadingTypes
  data: object
  title: string
  tabs?: {
    current: string;
    navigation: object;
  }
  invoices?: object
  refunds?: object
  in_process?: {}
}>()


const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);
let currentTab = ref();
let component = ref();

if (props.tabs) {
  currentTab = ref(props.tabs.current);
  component = computed(() => {
      const components = {
        invoices: TableInvoices,
        refunds: TableRefunds,
        in_process: TableInvoices
      };
      return components[currentTab.value];
  });
}


</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <template v-if="props.tabs">
        <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
        <component :is="component" :data="props[currentTab]" :resource="props[currentTab]" :tab="currentTab" :name="currentTab"></component>
    </template>
    <template v-else>
        <TableInvoices :data="data" />
    </template>
</template>

