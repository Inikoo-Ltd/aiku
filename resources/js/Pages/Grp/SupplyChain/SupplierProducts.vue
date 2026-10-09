<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 13 Sept 2024 15:27:43 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableSupplierProducts from "@/Components/Tables/Grp/SupplyChain/TableSupplierProducts.vue";
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading";
import { ref } from 'vue';
import Button from '@/Components/Elements/Buttons/Button.vue';
import { ctrans } from '@/Composables/useTrans'
import UploadExcel from '@/Components/Upload/UploadExcel.vue';
import UploadReports from '@/Components/Upload/UploadReports.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"

const props = defineProps <{
    pageHead: PageHeadingTypes
    title: string
    data: object
    upload_spreadsheet?: object
    tabs?: {
        current: string
        navigation: object
    }
    uploads?: object
}>()

const isModalUploadOpen = ref(false)

const currentTab = ref(props.tabs?.current ?? 'products')
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead">
      <template #other>
          <Button
              v-if="upload_spreadsheet"
              @click="() => isModalUploadOpen = true"
              :label="ctrans('Attach file')"
              icon="fal fa-upload"
              type="secondary"
          />
      </template>
    </PageHeading>
    <Tabs v-if="tabs" :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <UploadReports v-if="currentTab === 'uploads'" :data="uploads" tab="uploads" />
    <TableSupplierProducts v-else :data="data" />
    <UploadExcel
        v-model="isModalUploadOpen"
        scope="Supplier Product"
        :title="{
            label: 'Upload your new products',
            information: 'The list of column file: customer_reference, notes, stored_items'
        }"
        v-if="upload_spreadsheet"
        progressDescription="Adding Products to Supplier"        
        :upload_spreadsheet="upload_spreadsheet"
        
    />
    
</template>

