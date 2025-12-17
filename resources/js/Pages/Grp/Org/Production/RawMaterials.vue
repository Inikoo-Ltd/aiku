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
import { get } from 'lodash-es'
import Button from '@/Components/Elements/Buttons/Button.vue'
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"


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
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);
const dataModal = ref({ isModalOpen: false })
const component = computed(() => {

  const components = {
    raw_materials: TableRawMaterials,
    raw_materials_histories: TableHistories,
  };
  return components[currentTab.value];

});

const onUploadOpen = (action) => {
    dataModal.value.isModalOpen = true
    dataModal.value.uploadRoutes = action.route
}

console.log(props)

</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #button-group-upload="{ action }">
            <Button @click="() => onUploadOpen(action)" :style="action.style" :icon="action.icon"
                v-tooltip="action.tooltip" class="rounded-l rounded-r-none border-none" />
        </template>
  </PageHeading>
  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>

  <UploadExcel information="The list of column file: customer_reference, notes, stored_items"
        :propName="'pallet deliveries'" description="Adding Pallet Deliveries" :routes="{
        upload: get(dataModal, 'uploadRoutes', {}),
    }" :dataModal="dataModal" />
</template>

