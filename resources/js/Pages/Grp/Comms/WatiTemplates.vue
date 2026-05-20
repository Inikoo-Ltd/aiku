<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Created: Wednesday, 20 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableWatiTemplates from "@/Components/Tables/TableWatiTemplates.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";

defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
}>();
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableWatiTemplates :data="data" />
</template>
