<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeadingPublic.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import RetinaTableFamilies from '@/Components/Tables/Retina/RetinaTableFamilies.vue'

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {},
}>()

</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"/>
    <RetinaTableFamilies :data="data" ></RetinaTableFamilies>
</template>

