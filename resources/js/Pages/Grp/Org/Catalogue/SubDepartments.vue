<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableSubDepartments from "@/Components/Tables/Grp/Org/Catalogue/TableSubDepartments.vue";
import { capitalize } from "@/Composables/capitalize";
import { faSeedling } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"


library.add(faSeedling);

const props = defineProps<{
  pageHead: object
  title: string
  data: object
  index?: {}
  sales?: {}
  need_review?: {}
  tabs: {
        current: string
        navigation: {}
    },
}>();

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
  const components: any = {
    index: TableSubDepartments,
    need_review: TableSubDepartments,
  }

  return components[currentTab.value]
})
</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>
  <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
  <component :is="component" :key="currentTab" :tab="currentTab" :data="props[currentTab]"></component>
</template>
