<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableOutboxes from "@/Components/Tables/TableOutboxes.vue";
import { capitalize } from "@/Composables/capitalize"

import { library } from "@fortawesome/fontawesome-svg-core";
import { faInboxOut, faSortAlt, faProjectDiagram, faPhoneVolume, faBars } from "@fal";

library.add(faInboxOut, faSortAlt, faProjectDiagram, faPhoneVolume, faBars);


const props = defineProps<{
    data: object
    title: string
    pageHead: object
}>()


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableOutboxes :data="data" />
</template>

