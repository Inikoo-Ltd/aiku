
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 06 Jun 2026 09:22:41 Indochina Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { faDatabase } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed } from "vue"

library.add(faDatabase)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    publicSiteVisits: {
        daily: { day: string, views: number, visitors: number }[]
        visitors: number
        views: number
        top_referrer: string | null
    }
}>()

const sparklinePoints = computed(() => {
    const daily = props.publicSiteVisits.daily
    if (!daily.length) return ""
    const max = Math.max(...daily.map(d => Number(d.views)), 1)
    return daily.map((d, i) =>
        `${(i / Math.max(daily.length - 1, 1)) * 100},${28 - (Number(d.views) / max) * 26}`
    ).join(" ")
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4">
        <div class="w-64 rounded-lg border border-gray-200 p-4">
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-medium">aiku.io</span>
                <span class="text-xs text-gray-500">{{ trans("Last 7 days") }}</span>
            </div>
            <div class="mt-2 flex items-baseline gap-3">
                <span class="text-sm">{{ publicSiteVisits.visitors }} {{ trans("visitors") }}</span>
                <span class="text-xs text-gray-500">{{ publicSiteVisits.views }} {{ trans("views") }}</span>
            </div>
            <svg v-if="sparklinePoints" viewBox="0 0 100 28" class="mt-2 h-7 w-full" preserveAspectRatio="none">
                <polyline :points="sparklinePoints" fill="none" stroke="currentColor" stroke-width="1.5" class="text-indigo-500" />
            </svg>
            <div v-if="publicSiteVisits.top_referrer" class="mt-2 truncate text-xs text-gray-500">
                {{ trans("Top referrer") }}: {{ publicSiteVisits.top_referrer }}
            </div>
            <Link :href="route('grp.devops.aiku-public-analytics')" class="mt-3 block text-xs text-indigo-600 hover:underline">
                {{ trans("See more") }} →
            </Link>
        </div>
    </div>
</template>
