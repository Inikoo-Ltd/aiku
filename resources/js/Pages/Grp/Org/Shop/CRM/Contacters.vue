<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableProspects from "@/Components/Tables/Grp/Org/CRM/TableProspects.vue";
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { ref, computed } from 'vue'
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import { useTabChange } from "@/Composables/tab-change"
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import ProspectsDashboard from '@/Pages/Grp/Org/Shop/CRM/ProspectsDashboard.vue'
import UploadExcel from '@/Components/Upload/UploadExcel.vue';
import Button from '@/Components/Elements/Buttons/Button.vue';
import { trans } from 'laravel-vue-i18n'
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileInvoice } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faFileInvoice)

const props = defineProps<{
  title: string
      pageHead: PageHeadingTypes
      tabs: {
          current: string
          navigation: {}
      }
      dashboard : {}
      history : {}
      lists : {}
      prospects : {}
      contacted : {}
      failed : {}
      success : {}
      tagsList : {}
      tagRoute : {}
      upload_spreadsheet?: {}
}>()

console.log(props)

const isModalUploadOpen = ref(false)

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
      const components: {[key: string]: Component} = {
        dashboard: ProspectsDashboard,
        prospects: TableProspects,
        contacted: TableProspects,
        failed: TableProspects,
        success: TableProspects,
        mailshots: TableMailshots,
        history: TableHistories,
       /*  lists: TableProspectLists */
      }
  
      return components[currentTab.value]
  })

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
      <template #other>
          <Button
              v-if="upload_spreadsheet"
              @click="() => isModalUploadOpen = true"
              :label="trans('Attach file')"
              icon="fal fa-upload"
              type="secondary"
          />
      </template>
    </PageHeading>
    <UploadExcel
        v-model="isModalUploadOpen"
        scope="Prospect"
        :title="{
            label: 'Upload your new prospects',
            information: 'The list of column file: customer_reference, notes, stored_items'
        }"
        v-if="upload_spreadsheet"
        progressDescription="Adding Prospects to Shop"        
        :upload_spreadsheet="upload_spreadsheet"
        
    />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>

