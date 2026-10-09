<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import SeoBacklinksOverview from "@/Components/Seo/SeoBacklinksOverview.vue"
import SeoBacklinkGap from "@/Components/Seo/SeoBacklinkGap.vue"
import TableSeoReferringDomains from "@/Components/Tables/Grp/Org/Web/TableSeoReferringDomains.vue"
import TableSeoBacklinks from "@/Components/Tables/Grp/Org/Web/TableSeoBacklinks.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { Navigation } from "@/types/Tabs"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: { current: string, navigation: Navigation }
    domain: string | null
    isConfigured: boolean
    fetchRoute: routeType | null
    overview?: object | null
    referring_domains?: object | null
    backlinks?: object | null
    gap?: object | null
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabComponent = computed(() => ({
    overview: SeoBacklinksOverview,
    referring_domains: TableSeoReferringDomains,
    backlinks: TableSeoBacklinks,
    gap: SeoBacklinkGap,
})[currentTab.value])

const isFetching = ref(false)
const fetchError = ref<string | null>(null)

const fetchNow = () => {
    if (!props.fetchRoute) {
        return
    }

    router.post(route(props.fetchRoute.name, props.fetchRoute.parameters), {}, {
        preserveScroll: true,
        only: [currentTab.value],
        onStart: () => {
            isFetching.value = true
            fetchError.value = null
        },
        onError: (errors) => fetchError.value = Object.values(errors)[0] ?? null,
        onFinish: () => isFetching.value = false,
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                v-if="fetchRoute"
                type="secondary"
                :label="ctrans('Fetch now')"
                icon="fal fa-sync-alt"
                :loading="isFetching"
                v-tooltip="ctrans('Local only. Runs the weekly backlink fetch for this website and its competitors now, link lists included. Costs a few cents of DataForSEO credit.')"
                @click="fetchNow" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <p v-if="fetchError" role="alert" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-red-700 ring-1 ring-gray-200">
        {{ fetchError }}
    </p>

    <p v-if="!isConfigured" role="alert" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-gray-700 ring-1 ring-gray-200">
        {{ ctrans("DataForSEO is not set up, so no backlink is fetched. Add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD to the environment.") }}
    </p>

    <p v-if="!domain" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-gray-600 ring-1 ring-gray-200">
        {{ ctrans("This shop has no website, so it has no backlinks.") }}
    </p>

    <component
        v-else
        :is="tabComponent"
        :key="currentTab"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
        :domain="domain" />
</template>
