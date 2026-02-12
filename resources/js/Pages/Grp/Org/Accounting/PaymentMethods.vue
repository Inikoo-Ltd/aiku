<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup>
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TablePaymentMethods from '@/Components/Tables/Grp/Org/Accounting/TablePaymentMethods.vue';
import { capitalize } from "@/Composables/capitalize"

defineProps(['data', 'title', 'pageHead']);
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <TablePaymentMethods :data="data" />
</template>
