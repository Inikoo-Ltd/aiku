<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Aug 2023 20:30:48 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faAnalytics, faBrowser,
    faChartLine, faDraftingCompass, faRoad, faSlidersH, faUsersClass, faClock, faSeedling,
    faBroadcastTower,
    faSkull,
    faRocket, 
    faExternalLink
} from "@fal"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableWebpages from "@/Components/Tables/Grp/Org/Web/TableWebpages.vue"
import TableExternalLinks from "@/Components/Tables/Grp/Org/Web/TableExternalLinks.vue"
import { capitalize } from "@/Composables/capitalize"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import WebsiteShowcase from "@/Components/Showcases/Org/WebsiteShowcase.vue"
import TableWebUsers from "@/Components/Tables/Grp/Org/CRM/TableWebUsers.vue"
import WebsiteAnalytics from "@/Pages/Grp/Org/Web/WebsiteAnalytics.vue"
import TableRedirects from "@/Components/Tables/Grp/Org/Web/TableRedirects.vue"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";

library.add(
    faChartLine,
    faClock,
    faAnalytics,
    faUsersClass,
    faDraftingCompass,
    faSlidersH,
    faRoad,
    faBrowser,
    faSeedling,
    faBroadcastTower,
    faSkull,
    faRocket,
    faExternalLink
)


const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: object
    }
    webpages?: string
    changelog?: object
    showcase?: object
    web_users?: object
    redirects?: {}
    external_links?: {},
    analytics: object
}>()

console.log(props)
let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        webpages: TableWebpages,
        details: ModelDetails,
        analytics: WebsiteAnalytics,
        changelog: TableHistories,
        showcase: WebsiteShowcase,
        web_users: TableWebUsers,
        redirects: TableRedirects,
        external_links: TableExternalLinks
    }
    return components[currentTab.value]

})

</script>


<template>
    
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" ></component>
</template>
