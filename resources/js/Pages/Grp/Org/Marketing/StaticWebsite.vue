<!--
  - Author: Rifqi Taufiqurrohman
  - Created: Thu, 12 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import StaticWebsiteShowcase from "@/Components/Showcases/Org/StaticWebsiteShowcase.vue"
import TableStaticWebpages from "@/Components/Tables/Grp/Org/Web/TableStaticWebpages.vue"
import TableStaticWebsiteComponents from "@/Components/Tables/Grp/Org/Web/TableStaticWebsiteComponents.vue"
import TableStaticWebsiteSnapshots from "@/Components/Tables/Grp/Org/Web/TableStaticWebsiteSnapshots.vue"
import { useCurrentTab, useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { StaticWebsiteShowcaseData } from "@/types/StaticWebsiteShowcase"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBroadcastTower, faBrowser, faExclamationTriangle, faExternalLink, faEye, faGlobe, faHistory, faPuzzlePiece, faSeedling, faSkull, faTachometerAltFast } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faBroadcastTower, faBrowser, faExclamationTriangle, faExternalLink, faEye, faGlobe, faHistory, faPuzzlePiece, faSeedling, faSkull, faTachometerAltFast)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: { current: string, navigation: object }
    preview_url: string
    live_url: string
    live_domain_warning: string | null
    can_restore: boolean
    showcase?: StaticWebsiteShowcaseData
    real_user_speed_history?: object | null
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
        <div v-if="live_domain_warning" class="mx-4 mt-3 flex items-start gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5" fixed-width aria-hidden="true" />
            <span>{{ live_domain_warning }}</span>
        </div>
        <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

        <template v-if="currentTab === 'showcase'">
            <StaticWebsiteShowcase v-if="showcase" :data="showcase" :real-user-speed-history="real_user_speed_history" />
            <div v-else class="space-y-3 px-6 pt-4 lg:px-8">
                <div class="skeleton h-10 w-96 rounded" />
                <div class="skeleton h-48 rounded-xl" />
            </div>
        </template>

        <div v-else class="px-4">
            <component :is="tabComponent" v-if="props[currentTab]" :data="props[currentTab]" :tab="currentTab" :can-restore="can_restore" />
        </div>
    </div>
</template>
