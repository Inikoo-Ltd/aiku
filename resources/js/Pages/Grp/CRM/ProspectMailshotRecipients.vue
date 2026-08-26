<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import MailshotJourney from '@/Components/Navigation/MailshotJourney.vue'
import MailshotSubjectEdit from '@/Components/Workshop/Mailshot/MailshotSubjectEdit.vue'
import { faChevronDown, faFilter, faTimes, faPlus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import TableTemplateProspectRecipients from '@/Components/Tables/TableTemplateProspectRecipients.vue'
import { routeType } from '@/types/route'

// Import Datepicker
import '@vuepic/vue-datepicker/dist/main.css'

library.add(faChevronDown, faFilter, faTimes, faPlus)

const props = defineProps<{
    title: string
    pageHead: any
    mailshot: any
    journey: any
    mailshot_copy: { subject: string, name: string | null, preview_text: string | null }
    updateMailshotRoute: routeType
    suggestCopyRoute: routeType
    filtersStructure: Record<string, any>
    filters: any
    recipientFilterRoute: routeType
    recipients_recipe: any
    shop_slug: string
    shop_id: number
    estimatedRecipients: number
}>()

const savedSubject = ref<string | null>(null)
const pageHeadData = computed(() => savedSubject.value ? { ...props.pageHead, title: savedSubject.value } : props.pageHead)
</script>

<template>

    <Head :title="title" />

    <PageHeading :data="pageHeadData">
        <template #afterTitle2>
            <MailshotSubjectEdit :mailshot="mailshot_copy" :updateMailshotRoute="updateMailshotRoute"
                :suggestCopyRoute="suggestCopyRoute" @saved="subject => savedSubject = subject" />
            <MailshotJourney :steps="journey" class="ml-4" />
        </template>
    </PageHeading>

    <TableTemplateProspectRecipients :filters="filters" :filters-structure="filtersStructure"
        :recipient-filter-route="recipientFilterRoute" :recipients-recipe="recipients_recipe" :shop-id="shop_id"
        :shop-slug="shop_slug" :estimated-recipients="estimatedRecipients" />
</template>
