<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import  PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TableWebsiteVisitors from "@/Components/Tables/Grp/Org/Web/TableWebsiteVisitors.vue"
import { PageHeadingTypes } from "@/types/PageHeading";

defineProps <{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()

</script>
<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <TableWebsiteVisitors :data="data" />

</template>
