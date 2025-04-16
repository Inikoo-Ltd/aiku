<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 28 Jan 2025 01:32:11 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableRefunds from "@/Components/Tables/Grp/Org/Accounting/TableRefunds.vue";
import { capitalize } from "@/Composables/capitalize"
import {library} from '@fortawesome/fontawesome-svg-core';

import {
  faFileMinus
} from "@fal";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";

library.add(faFileMinus);

defineProps<{
  pageHead: PageHeadingTypes
  data: object
  title: string
}>()


</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <TableRefunds :data="data" />
</template>

