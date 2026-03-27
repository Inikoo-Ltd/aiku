<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import OfferTimelineCalendar from "@/Components/Discounts/OfferTimelineCalendar.vue"
import { capitalize } from "@/Composables/capitalize"
import type { PageHeadingTypes } from "@/types/PageHeading"

type CalendarHoliday = {
    date: string
    label: string
}

type HolidayRange = {
    from: string
    to: string
    label: string
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    calendar: {
        title?: string
        year?: number
        month?: number | null
        holidays?: CalendarHoliday[]
        holidayRanges?: HolidayRange[]
        campaignTypeLegend?: {
            type: string
            label: string
            color: string
        }[]
        organisationSlug?: string
        filters?: {
            campaign_type?: string | null
            shop?: number | null
            limit?: number | null
            year?: number
            month?: number | null
        }
        filterOptions?: {
            campaignTypes?: { value: string; label: string }[]
            shops?: { value: string; label: string }[]
        }
        pagination?: {
            total?: number
            loaded?: number
            limit?: number | null
            hasMore?: boolean
        }
    }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="px-4 pb-6 pt-2">
        <OfferTimelineCalendar :calendar="calendar" />
    </div>
</template>
