<!--
  - Author: Raul Perusquia <raul@inikoo.com>  
  - Created: Wed, 08 May 2024 14:59:21 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableProductions from "@/Components/Tables/Grp/Org/Production/TableProductions.vue";
import { capitalize } from "@/Composables/capitalize";
import { faBars, faIndustry } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import Tabs from "@/Components/Navigation/Tabs.vue";


library.add(faBars, faIndustry);


const props = defineProps<{
  pageHead: object
  tabs: {
    current: string;
    navigation: object;
  },
  title: string
  productions?: object
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

  const components = {
    productions: TableProductions

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

