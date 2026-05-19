<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 May 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableTemplates from "@/Components/Tables/TableTemplates.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
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
    <TableTemplates :data="data" />
</template>
