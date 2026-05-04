<!--
  - Author: stewicca
  - Created: 2026-04-06
  - Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import TableBrands from "@/Components/Tables/Grp/Goods/TableBrands.vue";

const props = defineProps<{
    title: string;
    pageHead: PageHeadingTypes;
    data: {};
}>();
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableBrands :data="data" />
</template>
