<!--
    -  Author: Raul Perusquia <raul@inikoo.com>
    -  Created: Mon, 09 Mar 2026, Kuala Lumpur, Malaysia
    -  Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { computed, ref } from "vue"
import type { Component } from 'vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import CampaignOverview from "@/Components/Shop/Offers/CampaignOverview.vue"
import { PageHeadingTypes } from '@/types/PageHeading'
import TableOffers from '@/Components/Shop/Offers/TableOffers.vue'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCommentDollar, faInfoCircle, faRepeat } from '@fal'

library.add(faCommentDollar, faInfoCircle, faRepeat)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    offers: {}
    overview: {
        offerCampaign: {}
        stats: {}
    }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        overview: CampaignOverview,
        offers: TableOffers
    }

    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
