<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableCollections from "@/Components/Tables/Grp/Org/Catalogue/TableCollections.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from '@/types/route'


defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    formData: {}
    website_domain : string
    routes : Array<routeType>
}>()

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
    </PageHeading>
    <TableCollections :data="data" :website_domain="website_domain" :routes="routes"/>

</template>