<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import RetinaTablePalletOrders from "@/Components/Tables/Retina/RetinaTablePalletOrders.vue";

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    orders: {}
    currency: {
        code: string
        symbol: string
        name: string
    }
}>()


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <RetinaTablePalletOrders :data="props.orders" :currency tab="orders" />
</template>
