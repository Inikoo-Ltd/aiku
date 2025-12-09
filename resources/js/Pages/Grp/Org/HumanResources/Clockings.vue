<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 17 Sept 2022 00:32:56 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableClockings from "@/Components/Tables/Grp/Org/HumanResources/TableClockings.vue";
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading";

const props = defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
}>()
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <TableClockings :data="data"/>
</template>


