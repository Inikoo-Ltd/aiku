<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:55:18 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { useTabChange } from "@/Composables/tab-change";
import { ref, computed } from 'vue'
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableDeliveryNoteItems from "@/Components/Warehouse/DeliveryNotes/TableDeliveryNoteItems.vue";


const props = defineProps<{
  data: object
  title: string
  pageHead: PageHeadingTypes
  items: object
  tabs: {
    current: string;
    navigation: object;
  }
}>();

console.log(props)

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

  const components = {
    items: TableDeliveryNoteItems,
  };
  return components[currentTab.value];

});


</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>
  <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
  <div class="pb-12">
    <component :is="component" :data="props[currentTab]" :tab="currentTab" />
  </div>

</template>
