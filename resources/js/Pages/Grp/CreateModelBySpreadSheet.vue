<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Fri, 20 june 2022 09:34:00 Central European Summer Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    documentName: string,
    sheetData: {
        columns: Array<{
            id: string
            name: string
            type: string
            prop: string
            required?: boolean
        }>
    }
    saveRoute: {
        name: string,
        arguments?: Array<string>
    }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <!-- <SpreadSheet
        :data="{ columns: props.sheetData.columns, value: [] }"
        :actionRoute="saveRoute"
        :documentName="documentName"
    ></SpreadSheet> -->
    <!-- <CustomTable
    :data="{ columns: props.sheetData.columns, value: [] }"
    :actionRoute="saveRoute"
    :documentName="documentName"
    /> -->
</template>
