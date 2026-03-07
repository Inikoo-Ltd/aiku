<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { ref, computed } from 'vue'
import { useTabChange } from "@/Composables/tab-change"
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import { trans } from 'laravel-vue-i18n'
import { PageHeadingTypes } from "@/types/PageHeading";
import type { Component } from 'vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileInvoice, faSeedling, faDownload } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faFileInvoice, faSeedling, faDownload)

const props = defineProps<{
  title: string
  pageHead: PageHeadingTypes
  tabs: {
    current: string
    navigation: {}
  }
  history: {}
  mailshots: {}
}>()

console.log(props)

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
  const components: { [key: string]: Component } = {
    mailshots: TableMailshots,
    history: TableHistories,
  }

  return components[currentTab.value]
})
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #other>
    </template>
  </PageHeading>

  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
