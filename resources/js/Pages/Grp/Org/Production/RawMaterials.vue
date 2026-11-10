<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 16 May 2024 11:03:23 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableRawMaterials from "@/Components/Tables/Grp/Org/Production/TableRawMaterials.vue";
import { capitalize } from "@/Composables/capitalize";
import { faBars, faIndustry } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { PageHeadingTypes } from "@/types/PageHeading";
import type { Navigation } from "@/types/Tabs";
import UploadExcel from '@/Components/Upload/UploadExcel.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { ctrans } from "@/Composables/useTrans"


library.add(faBars, faIndustry);


const props = defineProps<{
  pageHead: PageHeadingTypes
  tabs: {
    current: string;
    navigation: Navigation;
  },
  title: string
  raw_materials?: object
  raw_materials_histories?: {}
  upload_raw_materials?: {
    title: {
      label: string
      information: string
    }
    progressDescription: string
    upload_spreadsheet: object
    preview_template: {
      header: string[]
      rows: {}[]
    }
  }
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);
const isModalUploadOpen = ref(false)
const component = computed(() => {

  const components = {
    raw_materials: TableRawMaterials,
    raw_materials_histories: TableHistories,
  };
  return components[currentTab.value];

});

const download = (action: { route: { name: string, parameters: object } }) => {
  const search = new URLSearchParams(window.location.search).get('raw_materials_filter[global]')
  window.location.href = route(action.route.name, { ...action.route.parameters, ...(search ? { search } : {}) })
}

</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #button-download="{ action }">
      <Button @click="download(action)" :style="action.style" :icon="action.icon" :label="action.label"
        v-tooltip="ctrans('Excel file of the raw materials shown, all of them when nothing is searched')" />
    </template>
    <template #button-group-upload="{ action }">
      <Button @click="() => (isModalUploadOpen = true)" :style="action.style" :icon="action.icon"
        v-tooltip="action.tooltip" class="rounded-l rounded-r-none border-none" />
    </template>
  </PageHeading>
  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>

  <UploadExcel
    v-if="upload_raw_materials"
    v-model="isModalUploadOpen"
    :title="upload_raw_materials.title"
    :progressDescription="upload_raw_materials.progressDescription"
    :upload_spreadsheet="upload_raw_materials.upload_spreadsheet"
    :preview_template="upload_raw_materials.preview_template"
    :propsRefreshAfterFinish="['raw_materials']"
  />
</template>

