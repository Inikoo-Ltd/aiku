<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, Link, usePoll } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableSiteCrawls from "@/Components/Tables/Grp/Org/Web/TableSiteCrawls.vue"
import SiteCrawlerNewCrawlDialog, { NewCrawlSettings } from "@/Components/Seo/SiteCrawlerNewCrawlDialog.vue"
import SiteCrawlerScheduleDialog, { CrawlSchedule, ScheduleSettings } from "@/Components/Seo/SiteCrawlerScheduleDialog.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faLightbulb, faCalendarAlt, faPencil, faSpider } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faSpider)
import { routeType } from "@/types/route"
import { useLocaleStore } from "@/Stores/locale"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    website: { id: number, domain: string, slug: string } | null
    newCrawl: NewCrawlSettings
    suggestions: { pending: number, route: routeType } | null
    schedules: CrawlSchedule[]
    scheduleSettings: ScheduleSettings
    data: { data: Array<{ state: string }> }
}>()

const locale = useLocaleStore()

const isNewCrawlVisible = ref(false)
const isScheduleVisible = ref(false)
const editedSchedule = ref<CrawlSchedule | null>(null)

const openSchedule = (schedule: CrawlSchedule | null) => {
    editedSchedule.value = schedule
    isScheduleVisible.value = true
}

const weekdays = [ctrans("Sundays"), ctrans("Mondays"), ctrans("Tuesdays"), ctrans("Wednesdays"), ctrans("Thursdays"), ctrans("Fridays"), ctrans("Saturdays")]

const scheduleText = (schedule: CrawlSchedule) => {
    const time = `${String(schedule.hour).padStart(2, "0")}:00 UTC`

    if (schedule.frequency === "daily") {
        return ctrans("Every day at :time", { time })
    }

    if (schedule.frequency === "weekly") {
        return ctrans(":day at :time", { day: weekdays[schedule.weekday ?? 0], time })
    }

    return ctrans("Day :day of every month at :time", { day: schedule.day_of_month ?? 1, time })
}

const websiteSchedule = computed(() => props.website ? props.schedules.find((schedule) => schedule.website_id === props.website?.id) ?? null : null)

const unscheduledWebsites = computed(() => props.newCrawl.websites.filter((website) => !props.schedules.some((schedule) => schedule.website_id === website.id)))

const hasRunningCrawl = computed(() => props.data.data.some((crawl) => crawl.state !== "finish"))

const { start: startPolling, stop: stopPolling } = usePoll(10000, { only: ["data"] }, { autoStart: false })

watch(hasRunningCrawl, (isRunning) => isRunning ? startPolling() : stopPolling(), { immediate: true })
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Link v-if="suggestions" :href="route(suggestions.route.name, suggestions.route.parameters)">
                <Button type="tertiary" :label="ctrans('Content suggestions (:count)', { count: locale.number(suggestions.pending) })">
                    <template #icon>
                        <FontAwesomeIcon :icon="faLightbulb" class="text-amber-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </Link>
            <Button
                v-if="website ? (websiteSchedule?.can_edit || newCrawl.websites.some((startable) => startable.id === website?.id)) : unscheduledWebsites.length"
                type="tertiary"
                :label="websiteSchedule ? ctrans('Edit schedule') : ctrans('Schedule')"
                @click="openSchedule(websiteSchedule)"
            >
                <template #icon>
                    <FontAwesomeIcon :icon="faCalendarAlt" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <Button v-if="newCrawl.websites.length" type="tertiary" :label="ctrans('New crawl')" @click="isNewCrawlVisible = true">
                <template #icon>
                    <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>
    </PageHeading>

    <section v-if="schedules.length" class="mx-4 mt-3 rounded-md border border-gray-200 bg-white" aria-labelledby="schedules-title">
        <h2 id="schedules-title" class="border-b border-gray-100 px-3 py-2 text-sm font-semibold text-gray-900">{{ ctrans("Scheduled crawls") }}</h2>
        <ul class="divide-y divide-gray-100 text-sm">
            <li v-for="schedule in schedules" :key="schedule.website_id" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2">
                <span class="min-w-[10rem] font-medium text-gray-900">{{ schedule.domain }}</span>
                <span class="text-gray-700">{{ scheduleText(schedule) }}</span>
                <span v-if="!schedule.is_active" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">{{ ctrans("Paused") }}</span>
                <span v-else-if="schedule.next_run_at" class="text-xs text-gray-600">{{ ctrans("Next :date", { date: useFormatTime(schedule.next_run_at, { formatTime: "hm" }) }) }}</span>
                <button
                    v-if="schedule.can_edit"
                    type="button"
                    class="ml-auto flex h-8 w-8 cursor-pointer items-center justify-center rounded text-gray-600 hover:bg-gray-100 hover:text-[var(--app-accent)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--app-accent)]"
                    :aria-label="ctrans('Edit the schedule of :website', { website: schedule.domain })"
                    @click="openSchedule(schedule)"
                >
                    <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
                </button>
            </li>
        </ul>
    </section>

    <TableSiteCrawls :data="data" />

    <SiteCrawlerScheduleDialog v-model:visible="isScheduleVisible" :settings="scheduleSettings" :websites="unscheduledWebsites" :schedule="editedSchedule" :websiteId="website?.id" />

    <SiteCrawlerNewCrawlDialog v-model:visible="isNewCrawlVisible" :settings="newCrawl" :websiteId="website?.id" />
</template>
