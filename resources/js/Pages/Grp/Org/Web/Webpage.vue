<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 13 Sep 2023 23:58:37 Malaysia Time, Pantai Lembeng, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { capitalize } from "@/Composables/capitalize"
import { computed, inject, onMounted, onUnmounted, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"

import ModelDetails from "@/Components/ModelDetails.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableWebpages from "@/Components/Tables/Grp/Org/Web/TableWebpages.vue"
import TableExternalLinks from "@/Components/Tables/Grp/Org/Web/TableExternalLinks.vue"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { library } from '@fortawesome/fontawesome-svg-core'

import { faUsersClass, faAnalytics, faBrowser, faChartLine, faDraftingCompass, faRoad, faSlidersH, faClock, faLevelDown, faShapes, faSortAmountDownAlt, faLayerGroup, faExternalLink,faObjectGroup ,faDirections} from '@fal'
import WebpageShowcase from "@/Components/Showcases/Org/WebpageShowcase.vue"
import WebpageAnalytics from "@/Components/DataDisplay/WebpageAnalytics.vue"
import TableWebpageTrafficSources from "@/Components/Tables/Grp/Org/Web/TableWebpageTrafficSources.vue"
import TableSnapshots from "@/Components/Tables/TableSnapshots.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { layoutStructure } from '@/Composables/useLayoutStructure'
import TableRedirects from '@/Components/Tables/Grp/Org/Web/TableRedirects.vue'
import WebpageLockBanner from '@/Components/CMS/Webpage/WebpageLockBanner.vue'
import WebpageLockButton from '@/Components/CMS/Webpage/WebpageLockButton.vue'
import { faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull, faRoute } from '@fal'
import { ctrans } from '@/Composables/useTrans'
library.add(faRoute, faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull, faChartLine, faClock, faUsersClass, faAnalytics, faDraftingCompass, faSlidersH, faRoad, faLayerGroup, faBrowser, faLevelDown, faShapes, faSortAmountDownAlt, faExternalLink,faObjectGroup,faDirections)

const props = defineProps<{
    title: string
    pageHead: any
    tabs: {
        current: string
        navigation: object
    }
    root_active?: string  // To manipulate the route active (SubNavigation)
    webpages?: object
    changelog?: object
    showcase?: any
    snapshots?: object,
    redirects?: {},
    external_links?: {}
    labeled_snapshots?: {}
    analytics?:any
    traffic_sources?: any
    real_user_speed?: any
    engagement?: any
    seo?: any
    structured_data_source?: any
    webpage_canonical_url?: string
    redirected_to?: {}
    closed?: { closed_at: string | null, closed_by: string | null } | null
    lock: any
    can_edit?: boolean
}>()


const currentTab = ref(props.tabs.current)
const deferredPropsOfTab = {
    showcase: ['real_user_speed', 'engagement', 'structured_data_source'],
    analytics: ['real_user_speed'],
}
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab, deferredPropsOfTab[tabSlug] ?? [])

const component = computed(() => {
    const components = {
        'details': ModelDetails,
        'changelog': TableHistories,
        'showcase': WebpageShowcase,
        'analytics': WebpageAnalytics,
        'traffic_sources': TableWebpageTrafficSources,
        'webpages': TableWebpages,
        'snapshots': TableSnapshots,
        'redirects': TableRedirects,
        'external_links': TableExternalLinks,
        'labeled_snapshots': TableSnapshots
    }

    return components[currentTab.value]
})

const layout = inject('layout', layoutStructure)

onMounted(() => {
    layout.root_active = props.root_active
})

onUnmounted(() => {
    layout.root_active = null
})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <WebpageLockButton v-if="lock && can_edit" :lock="lock" />
        </template>
        <template #other>
            <a v-if="webpage_canonical_url" :href="webpage_canonical_url" target="_blank" class="text-gray-400 hover:text-gray-700 px-2 cursor-pointer" v-tooltip="ctrans('Open website in new tab')" aclick="openWebsite" >
                <FontAwesomeIcon :icon="faExternalLink" fixed-width aria-hidden="true" size="xl" />
            </a>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <WebpageLockBanner v-if="lock" :lock="lock" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]" :real_user_speed="real_user_speed" :engagement="engagement" :seo="seo" :structured_data_source="structured_data_source" :redirected_to="redirected_to" :closed="closed" :editable="lock?.can_edit ?? true" :canEdit="can_edit"></component>
</template>
