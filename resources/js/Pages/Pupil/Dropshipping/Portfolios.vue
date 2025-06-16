<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"


import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import PupilTablePortfolios from "@/Components/Tables/Retina/PupilTablePortfolios.vue";

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    products: {}
}>()



</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <PupilTablePortfolios :data="props.products" :location="'pupil'" :tab="'products'" />
</template>
