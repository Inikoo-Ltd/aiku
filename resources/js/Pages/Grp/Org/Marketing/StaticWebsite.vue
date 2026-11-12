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
import TableStaticWebpages from "@/Components/Tables/Grp/Org/Web/TableStaticWebpages.vue"
import TableStaticWebsiteComponents from "@/Components/Tables/Grp/Org/Web/TableStaticWebsiteComponents.vue"
import TableStaticWebsiteSnapshots from "@/Components/Tables/Grp/Org/Web/TableStaticWebsiteSnapshots.vue"
import { useCurrentTab, useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBroadcastTower, faBrowser, faExternalLink, faEye, faGlobe, faHistory, faPuzzlePiece, faSeedling, faSkull, faTachometerAltFast } from "@fal"

library.add(faBroadcastTower, faBrowser, faExternalLink, faEye, faGlobe, faHistory, faPuzzlePiece, faSeedling, faSkull, faTachometerAltFast)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: { current: string, navigation: object }
    preview_url: string
    live_url: string
    can_restore: boolean
    showcase?: {
        stats: { label: string, value: number, icon: string, route?: { name: string, parameters: (string | number)[] }, tab?: string }[]
        details: { label: string, value: string | number | null, href?: string | null, hint?: string, type?: "date" }[]
    }
    webpages?: object
    components?: object
    snapshots?: object
}>()

const currentTab = useCurrentTab(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabComponent = computed(() => ({
    webpages: TableStaticWebpages,
    components: TableStaticWebsiteComponents,
    snapshots: TableStaticWebsiteSnapshots,
}[currentTab.value]))
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

        <div v-if="currentTab === 'showcase'" class="max-w-5xl space-y-4 px-4 py-4">
            <template v-if="showcase">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <component :is="stat.route ? Link : 'button'" v-for="stat in showcase.stats" :key="stat.label"
                               :href="stat.route ? route(stat.route.name, stat.route.parameters) : undefined"
                               type="button" @click="stat.tab ? handleTabUpdate(stat.tab) : undefined"
                               class="flex items-center gap-3 rounded-xl bg-white p-4 text-left ring-1 ring-gray-200 hover:ring-[var(--app-accent)]">
                        <FontAwesomeIcon :icon="stat.icon" class="text-xl text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                        <span>
                            <span class="block text-xs text-gray-500">{{ stat.label }}</span>
                            <span class="block text-lg tabular-nums text-gray-900">{{ stat.value }}</span>
                        </span>
                    </component>
                </div>
                <StaticWebsiteDetails :items="showcase.details" />
            </template>
            <div v-else class="space-y-3">
                <div class="skeleton h-20 rounded-xl" />
                <div class="skeleton h-48 rounded-xl" />
            </div>
        </div>

        <div v-else class="px-4">
            <component :is="tabComponent" v-if="props[currentTab]" :data="props[currentTab]" :tab="currentTab" :can-restore="can_restore" />
        </div>
    </div>
</template>
