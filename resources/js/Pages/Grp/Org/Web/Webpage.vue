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
import TableSnapshots from "@/Components/Tables/TableSnapshots.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { layoutStructure } from '@/Composables/useLayoutStructure'
import TableRedirects from '@/Components/Tables/Grp/Org/Web/TableRedirects.vue'
import { faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull } from '@fal'
import { trans } from 'laravel-vue-i18n'
library.add(faHome, faSignIn, faHammer, faCheckCircle, faBroadcastTower, faSkull, faChartLine, faClock, faUsersClass, faAnalytics, faDraftingCompass, faSlidersH, faRoad, faLayerGroup, faBrowser, faLevelDown, faShapes, faSortAmountDownAlt, faExternalLink,faObjectGroup,faDirections)

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
    analytics:any
}>()


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components = {
        'details': ModelDetails,
        'changelog': TableHistories,
        'showcase': WebpageShowcase,
        'analytics': WebpageAnalytics,
        'webpages': TableWebpages,
        'snapshots': TableSnapshots,
        'redirects': TableRedirects,
        'external_links': TableExternalLinks
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
        <template #other>
            <a :href="showcase.url" target="_blank" class="text-gray-400 hover:text-gray-700 px-2 cursor-pointer" v-tooltip="trans('Open website in new tab')" aclick="openWebsite" >
                <FontAwesomeIcon :icon="faExternalLink" aria-hidden="true" size="xl" />
            </a>
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
</template>
