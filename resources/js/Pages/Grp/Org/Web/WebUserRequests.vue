
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Jun 2025 20:33:45 British Summer Time, Sheffield, UK
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import  PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TableWebUserRequests from "@/Components/Tables/Grp/Org/Web/TableWebUserRequests.vue"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import TableWebUsers from "@/Components/Tables/Grp/Org/CRM/TableWebUsers.vue";

defineProps <{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()

</script>
<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <TableWebUserRequests :data="data" />

</template>