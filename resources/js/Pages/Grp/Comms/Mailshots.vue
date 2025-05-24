<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faInboxIn, faDumpsterFire, faThumbsDown } from "@fal";

library.add(faInboxIn, faDumpsterFire, faThumbsDown);


defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
}>();

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableMailshots :data="data" />
</template>

