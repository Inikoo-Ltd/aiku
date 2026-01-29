<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { ref, watch, computed } from 'vue'
import { faChevronDown, faFilter, faTimes, faPlus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import TableTemplateRecipients from '@/Components/Tables/TableTemplateRecipients.vue'
import { routeType } from '@/types/route'

// Import Datepicker
import '@vuepic/vue-datepicker/dist/main.css'

library.add(faChevronDown, faFilter, faTimes, faPlus)

const props = defineProps<{
    title: string
    pageHead: any
    mailshot: any
    filtersStructure: Record<string, any>
    filters: any
    recipientFilterRoute: routeType
    recipients_recipe: any
    shop_slug: string
    shop_id: number
    estimatedRecipients: number
}>()
</script>

<template>

    <Head :title="title" />

    <PageHeading :data="pageHead" />

    <Notification v-if="showNotification && notificationData" :notification="notificationData" />

    <TableTemplateRecipients :filters="filters" :filters-structure="filtersStructure"
        :recipient-filter-route="recipientFilterRoute" :recipients-recipe="recipients_recipe" :shop-id="shop_id"
        :shop-slug="shop_slug" :estimated-recipients="estimatedRecipients" />
</template>
