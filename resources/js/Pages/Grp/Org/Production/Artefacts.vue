<!--
  - Author: Raul Perusquia <raul@inikoo.com>  
  - Created: Wed, 08 May 2024 14:59:21 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableArtefacts from "@/Components/Tables/Grp/Org/Production/TableArtefacts.vue";
import { capitalize } from "@/Composables/capitalize";
import { faBars, faIndustry } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { PageHeadingTypes } from "@/types/PageHeading";
import type { Navigation } from "@/types/Tabs";
import UploadExcel from "@/Components/Upload/UploadExcel.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";


library.add(faBars, faIndustry);


const props = defineProps<{
  pageHead: PageHeadingTypes
  tabs: {
    current: string;
    navigation: Navigation;
  },
  title: string
  artefacts?: object
  move_to_department?: object
  move_to_family?: object
  set_batch_size?: object
  set_shelf_life?: object
  set_state?: object
  upload_artefacts?: {
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
    artefacts: TableArtefacts

  };
  return components[currentTab.value];

});

</script>

<!--suppress HtmlUnknownAttribute -->
<template>
  <!--suppress HtmlRequiredTitleElement -->
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #button-group-upload="{ action }">
      <Button @click="() => (isModalUploadOpen = true)" :style="action.style" :icon="action.icon"
        v-tooltip="action.tooltip" class="rounded-l-md rounded-r-none border-none" />
    </template>
  </PageHeading>
  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component :is="component" :tab="currentTab" :data="props[currentTab]" :moveToDepartment="move_to_department" :moveToFamily="move_to_family" :setBatchSize="set_batch_size" :setShelfLife="set_shelf_life" :setState="set_state"></component>

  <UploadExcel
    v-if="upload_artefacts"
    v-model="isModalUploadOpen"
    :title="upload_artefacts.title"
    :progressDescription="upload_artefacts.progressDescription"
    :upload_spreadsheet="upload_artefacts.upload_spreadsheet"
    :preview_template="upload_artefacts.preview_template"
    :propsRefreshAfterFinish="['artefacts']"
  />
</template>

