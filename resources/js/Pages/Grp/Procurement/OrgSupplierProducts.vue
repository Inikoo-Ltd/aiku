<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableOrgSupplierProducts from "@/Components/Tables/Grp/Org/Procurement/TableOrgSupplierProducts.vue";
import { capitalize } from "@/Composables/capitalize"

defineProps <{
    pageHead: object
    title: string
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <TableOrgSupplierProducts :data="data" />
</template>

