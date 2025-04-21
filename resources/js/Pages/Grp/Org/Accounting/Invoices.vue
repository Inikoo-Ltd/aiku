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
import { Icon } from "@/types/Utils/Icon";
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faOmega } from '@fas'

library.add(faFileMinus, faArrowCircleLeft, faOmega);

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
  invoiceExportOptions: {
    type: string
    name: string
    label: string
    parameters: any
    tooltip: string
    icon: Icon
  }[]
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
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div v-if="props.invoiceExportOptions?.length" class="flex flex-wrap border border-gray-300 rounded-md overflow-hidden h-fit">
                <a v-for="exportOption in props.invoiceExportOptions"
                :href="exportOption.name ? route(exportOption.name, exportOption.parameters) : '#'"
                class="w-auto mt-0 sm:flex-none text-base"
                v-tooltip="exportOption.tooltip"
                >
                <Button
                    :label="exportOption.label"
                    :icon="exportOption.icon"
                    type="tertiary"
                    class="rounded-none border-transparent"
                />
                </a>
            </div>
        </template>
    </PageHeading>
    <template v-if="props.tabs">
        <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
        <component :is="component" :data="props[currentTab]" :resource="props[currentTab]" :tab="currentTab" :name="currentTab"></component>
    </template>
    <template v-else>
        <TableInvoices :data="data" />
    </template>
</template>

