<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { computed, ref } from "vue"
import type { Component } from 'vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import StepOffersCampaignOverview from "@/Components/Shop/Offers/StepOffersCampaignOverview.vue"
import { PageHeadingTypes } from '@/types/PageHeading'
import TableOffers from '@/Components/Shop/Offers/TableOffers.vue'

import { library } from "@fortawesome/fontawesome-svg-core"
import { faCommentDollar, faInfoCircle, faSortAmountUp } from '@fal'

library.add(faCommentDollar, faInfoCircle, faSortAmountUp)


const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    offers?: {}
    overview?: {}
    shop_data: {
        slug: string
        currency_code: string
    }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        overview: StepOffersCampaignOverview,
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
