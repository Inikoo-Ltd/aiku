<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { ref, watch, computed } from 'vue'
import { faChevronDown, faFilter, faTimes, faPlus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import TableTemplateRecipients from '@/Components/Tables/TableTemplateRecipients.vue'

// Import Datepicker
import '@vuepic/vue-datepicker/dist/main.css'

library.add(faChevronDown, faFilter, faTimes, faPlus)

const props = defineProps<{
    title: string
    pageHead: any
    mailshot: any
    filtersStructure: Record<string, any>
    filters: any
    customers: any
}>()
</script>

<template>

    <Head :title="title" />

    <PageHeading :data="pageHead" />

    <TableTemplateRecipients :customers="customers" :filters="filters" :filters-structure="filtersStructure" />
</template>
