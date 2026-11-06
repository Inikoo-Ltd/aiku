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
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faLightbulb } from "@fal"
import { routeType } from "@/types/route"
import { useLocaleStore } from "@/Stores/locale"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    website: { id: number, domain: string, slug: string } | null
    newCrawl: NewCrawlSettings
    suggestions: { pending: number, route: routeType } | null
    data: { data: Array<{ state: string }> }
}>()

const locale = useLocaleStore()

const isNewCrawlVisible = ref(false)

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
            <Button v-if="newCrawl.websites.length" type="tertiary" :label="ctrans('New crawl')" @click="isNewCrawlVisible = true">
                <template #icon>
                    <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>
    </PageHeading>

    <TableSiteCrawls :data="data" />

    <SiteCrawlerNewCrawlDialog v-model:visible="isNewCrawlVisible" :settings="newCrawl" :websiteId="website?.id" />
</template>
