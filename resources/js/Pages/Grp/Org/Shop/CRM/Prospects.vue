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
import { ctrans } from '@/Composables/useTrans'
import { PageHeadingTypes } from "@/types/PageHeading";
import type { Component } from 'vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileInvoice, faSeedling, faDownload, faUserPlus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from "@/types/route"
library.add(faFileInvoice, faSeedling, faDownload, faUserPlus)

const props = defineProps<{
  title: string
      pageHead: PageHeadingTypes
      tabs: {
          current: string
          navigation: {}
      }
      history?: {}
      prospects?: {}
      opt_in?: {}
      opt_out?: {}
      contacted?: {}
      failed?: {}
      success?: {}
      upload_spreadsheet?: {}
      download_route: {
        xlsx: routeType
        csv: routeType
      }
}>()


const isModalUploadOpen = ref(false)

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
      const components: {[key: string]: Component} = {
        prospects: TableProspects,
        opt_in: TableProspects,
        opt_out: TableProspects,
        contacted: TableProspects,
        failed: TableProspects,
        success: TableProspects,
        mailshots: TableMailshots,
        history: TableHistories,
       /*  lists: TableProspectLists */
      }

      return components[currentTab.value]
  })


const downloadUrl = (type: string) => {
    if (props.download_route?.[type]?.name) {
        return route(props.download_route[type].name, props.download_route[type].parameters);
    } else {
        return ''
    }
};
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
      <template #other>

          <div v-if="currentTab === 'prospects'" class="rounded-md ">
              <a :href="(downloadUrl('csv') as string)" target="_blank" rel="noopener">
                  <Button :icon="faDownload" label="CSV" type="tertiary" class="rounded-r-none"/>
              </a>

              <a :href="(downloadUrl('xlsx') as string)" target="_blank" rel="noopener">
                  <Button :icon="faDownload" label="xlsx" type="tertiary" class="border-l-0  rounded-l-none"/>
              </a>
            </div>

          <Button
              v-if="upload_spreadsheet"
              @click="() => isModalUploadOpen = true"
              :label="ctrans('Attach file')"
              icon="fal fa-upload"
              type="secondary"
          />
      </template>
    </PageHeading>

    <UploadExcel
        v-model="isModalUploadOpen"
        scope="Prospect"
        :title="{
            label: ctrans('Upload your new prospects'),
            information: ctrans('Make sure your file has a contact name column (Contact name, Name or Full name). Email, Company, Phone and Prospect key columns are read too, in any order. To update a prospect, put its id in Prospect key; leave the column out, or write new, to add one.')
        }"
        v-if="upload_spreadsheet"
        progressDescription="Adding Prospects to Shop"
        :upload_spreadsheet="upload_spreadsheet"
        :propsRefreshAfterFinish="[currentTab]"
    />

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>

