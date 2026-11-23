<!--
  - Author: Rifqi Taufiqurrohman
  - Created: Thu, 12 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import StaticWebsiteDetails from "@/Components/Web/StaticWebsiteDetails.vue"
import StaticWebpageShowcase from "@/Components/Showcases/Org/StaticWebpageShowcase.vue"
import TableStaticWebsiteSnapshots from "@/Components/Tables/Grp/Org/Web/TableStaticWebsiteSnapshots.vue"
import { useCurrentTab, useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { StaticWebpageShowcaseData } from "@/types/StaticWebsiteShowcase"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBrowser, faExternalLink, faEye, faHistory, faPuzzlePiece, faTachometerAltFast } from "@fal"

library.add(faBrowser, faExternalLink, faEye, faHistory, faPuzzlePiece, faTachometerAltFast)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: { current: string, navigation: object }
    preview_url: string
    live_url: string
    can_restore: boolean
    showcase?: {
        details: { label: string, value: string | number | null, href?: string | null, hint?: string, type?: "date", code?: boolean }[]
        shared_parts?: { label: string, route: { name: string, parameters: (string | number)[] } }[]
        note?: string
    } | StaticWebpageShowcaseData
    real_user_speed?: object | null
    snapshots?: object
}>()

const webpageShowcase = computed(() => (props.showcase && "seo" in props.showcase ? props.showcase : null))
const partShowcase = computed(() => (props.showcase && !("seo" in props.showcase) ? props.showcase : null))

const currentTab = useCurrentTab(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
</script>

<template>
    <div>
        <Head :title="capitalize(title)" />
        <PageHeading :data="pageHead">
            <template #other>
                <div class="flex gap-2">
                    <a :href="live_url" target="_blank" rel="noopener">
                        <Button type="tertiary" icon="fal fa-external-link" :label="ctrans('Live domain')" />
                    </a>
                    <a :href="preview_url" target="_blank" rel="noopener">
                        <Button type="tertiary" icon="fal fa-eye" :label="ctrans('Preview in Aiku')" />
                    </a>
                </div>
            </template>
        </PageHeading>
        <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

        <StaticWebpageShowcase v-if="currentTab === 'showcase' && webpageShowcase" :data="webpageShowcase" :real-user-speed="real_user_speed" />

        <div v-else-if="currentTab === 'showcase'" class="max-w-5xl space-y-4 px-4 py-4">
            <template v-if="partShowcase">
                <StaticWebsiteDetails :items="partShowcase.details" />
                <div v-if="partShowcase.shared_parts?.length" class="rounded-xl bg-white p-4 text-sm ring-1 ring-gray-200">
                    <div class="text-gray-500">{{ ctrans("This page shows the site's shared parts") }}</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Link v-for="part in partShowcase.shared_parts" :key="part.label" :href="route(part.route.name, part.route.parameters)">
                            <Button type="tertiary" size="xs" icon="fal fa-puzzle-piece" :label="part.label" />
                        </Link>
                    </div>
                </div>
                <p v-if="partShowcase.note" class="text-xs text-gray-500">{{ partShowcase.note }}</p>
            </template>
            <div v-else class="skeleton h-48 rounded-xl" />
        </div>

        <div v-else class="px-4">
            <TableStaticWebsiteSnapshots v-if="snapshots" :data="snapshots" tab="snapshots" :can-restore="can_restore" />
        </div>
    </div>
</template>
