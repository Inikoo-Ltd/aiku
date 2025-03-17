<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 20:33:56 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableWarehouseAreas from "@/Components/Tables/Grp/Org/Inventory/TableWarehouseAreas.vue";
import { capitalize } from "@/Composables/capitalize";
import { library } from "@fortawesome/fontawesome-svg-core";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { get } from 'lodash-es'
import UploadExcel from "@/Components/Upload/UploadExcel.vue";
import { UploadPallet } from '@/types/Pallet'
import {
    faIndustryAlt
} from "@fal";
library.add(faIndustryAlt);


interface UploadSection {
    title: {
        label: string
        information: string
    }
    progressDescription: string
    upload_spreadsheet: UploadPallet
    preview_template: {
        header: string[]
        rows: {}[]
    }
}


const props = defineProps<{
    data: {}
    title: string
    pageHead: {}
    upload_warehouse_areas: UploadSection
}>();


console.log(props)

const dataModal = ref({ isModalOpen: false });



const isModalUploadOpen = ref(false)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-group-upload="{ action }">
            <Button
                @click="() => isModalUploadOpen = true"
                :style="'upload'"
                class="rounded-r-none text-sm border-none focus:ring-transparent focus:ring-offset-transparent focus:ring-0"
            />
        </template>
    </PageHeading>
    <TableWarehouseAreas :data="data" />

    
    <UploadExcel
        v-model="isModalUploadOpen"
        :title="upload_warehouse_areas?.title"
        :progressDescription="upload_warehouse_areas?.progressDescription"
        :upload_spreadsheet="upload_warehouse_areas?.upload_spreadsheet"
        :preview_template="upload_warehouse_areas?.preview_template"
    />
</template>

