<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Mon, 21 Apr 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableBatchCodes from "@/Components/Tables/Grp/Org/Inventory/TableBatchCodes.vue"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { UploadPallet } from "@/types/Pallet"

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

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: object
    upload_batch_codes: UploadSection
}>()

const isModalUploadOpen = ref(false)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-group-upload="{ action }">
            <Button
                @click="() => (isModalUploadOpen = true)"
                :style="'upload'"
                class="rounded-r-none text-sm border-none focus:ring-transparent focus:ring-offset-transparent focus:ring-0"
            />
        </template>
    </PageHeading>
    <TableBatchCodes :data="data" />

    <UploadExcel
        v-model="isModalUploadOpen"
        :title="upload_batch_codes?.title"
        :progressDescription="upload_batch_codes?.progressDescription"
        :upload_spreadsheet="upload_batch_codes?.upload_spreadsheet"
        :preview_template="upload_batch_codes?.preview_template"
        :propsRefreshAfterFinish="['data']"
    />
</template>
