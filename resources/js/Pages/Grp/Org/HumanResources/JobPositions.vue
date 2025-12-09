<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 15 Jun 2023 15:18:38 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { capitalize } from "@/Composables/capitalize"
import TableJobPositions from "@/Components/Tables/Grp/Org/HumanResources/TableJobPositions.vue";
import { PageHeadingTypes } from "@/types/PageHeading";

defineProps <{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableJobPositions :data="data" />
</template>

