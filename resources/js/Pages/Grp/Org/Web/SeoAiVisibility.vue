<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChartLine, faComments, faLink, faSyncAlt } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SeoAiVisibilityOverview from "@/Components/Seo/SeoAiVisibilityOverview.vue"
import SeoAiPrompts from "@/Components/Seo/SeoAiPrompts.vue"
import SeoAiCitedPages from "@/Components/Seo/SeoAiCitedPages.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { Navigation } from "@/types/Tabs"

library.add(faChartLine, faComments, faLink, faSyncAlt)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: { current: string, navigation: Navigation }
    canEdit: boolean
    isUsable: boolean
    isConfigured: boolean
    runRoute: routeType | null
    overview?: object | null
    prompts?: object | null
    cited_pages?: object | null
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabComponent = computed(() => ({
    overview: SeoAiVisibilityOverview,
    prompts: SeoAiPrompts,
    cited_pages: SeoAiCitedPages,
})[currentTab.value])

const isRunning = ref(false)
const runError = ref<string | null>(null)

const run = () => {
    if (!props.runRoute) {
        return
    }

    router.post(route(props.runRoute.name, props.runRoute.parameters), {}, {
        preserveScroll: true,
        only: [currentTab.value],
        onStart: () => {
            isRunning.value = true
            runError.value = null
        },
        onError: (errors) => runError.value = Object.values(errors)[0] ?? null,
        onFinish: () => isRunning.value = false,
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template v-if="runRoute" #other>
            <Button
                type="secondary"
                :label="ctrans('Run now')"
                icon="fal fa-sync-alt"
                :loading="isRunning"
                v-tooltip="ctrans('Local only. Asks ChatGPT up to 5 of the prompts that are due, about 30 seconds each, and fetches LLM Mentions if this month has none.')"
                @click="run" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <p v-if="runError" role="alert" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-red-700 ring-1 ring-gray-200">
        {{ runError }}
    </p>

    <p v-if="!isConfigured" role="alert" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-gray-700 ring-1 ring-gray-200">
        {{ ctrans("DataForSEO is not set up, so no prompt is sent to ChatGPT. Add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD to the environment.") }}
    </p>

    <p v-if="!isUsable" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-gray-600 ring-1 ring-gray-200">
        {{ ctrans("This shop needs a website before ChatGPT's answers can be checked for it.") }}
    </p>

    <component
        v-else
        :is="tabComponent"
        :key="currentTab"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
        :canEdit="canEdit" />
</template>
