<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 15 Feb 2024 02:24:05 Malaysia Time, Madrid Spain
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableWebUsers from "@/Components/Tables/Grp/Org/CRM/TableWebUsers.vue";
import { capitalize } from "@/Composables/capitalize"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faTerminal } from '@fal'
import { faClock } from '@far'
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
library.add(faTerminal, faClock)


defineProps <{
    pageHead: PageHeadingTypes
    title: string
    data:object

}>()



</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <TableWebUsers :data="data" />
   </template>

