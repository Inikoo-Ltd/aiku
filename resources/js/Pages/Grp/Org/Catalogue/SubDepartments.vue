<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableSubDepartments from "@/Components/Tables/Grp/Org/Catalogue/TableSubDepartments.vue";
import { capitalize } from "@/Composables/capitalize";
import { faSeedling } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(faSeedling);


defineProps<{
    pageHead: object
    title: string
    data: object
}>();

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableSubDepartments :data="data" />
</template>

