<!--
  - Author: Raul Perusquia <raul@inikoo.com>  
  - Created: Wed, 08 May 2024 14:59:21 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableManufactureTasks from "@/Components/Tables/Grp/Org/Production/TableManufactureTasks.vue";
import { capitalize } from "@/Composables/capitalize";
import { faBars, faIndustry } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import Tabs from "@/Components/Navigation/Tabs.vue";
import type { Navigation } from "@/types/Tabs";
import { PageHeadingTypes } from "@/types/PageHeading";


library.add(faBars, faIndustry);


const props = defineProps<{
  pageHead: PageHeadingTypes
  tabs: {
    current: string;
    navigation: Navigation;
  },
  title: string
  manufacture_tasks?: object
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

  const components = {
    manufacture_tasks: TableManufactureTasks

  };
  return components[currentTab.value];

});

</script>

<!--suppress HtmlUnknownAttribute -->
<template>
  <!--suppress HtmlRequiredTitleElement -->
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>
  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
</template>

