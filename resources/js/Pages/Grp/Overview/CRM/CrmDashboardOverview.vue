<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableTopCustomers from '@/Components/Tables/Grp/Org/CRM/TableTopCustomers.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    isGroup: boolean
    top_customers: any
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="mt-4">
        <TableTopCustomers :data="props.top_customers" />
    </div>
</template>

