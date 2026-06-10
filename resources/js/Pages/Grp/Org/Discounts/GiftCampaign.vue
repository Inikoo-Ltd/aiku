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
import { faCommentDollar, faInfoCircle, faGift } from '@fal'
import ModalCreateGiftOffers from '@/Components/Offers/ModalCreateGiftOffers.vue'

library.add(faCommentDollar, faInfoCircle, faGift)

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
    shop_data: {
        id: number
        slug?: string        
        organisation?: string
        offercampaign?: string
        currency_code: string
        default_dates: {
            start: string
            end: string
        }
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
    <PageHeading :data="pageHead">
        <template #other>
            <ModalCreateGiftOffers :shop_data="props.shop_data" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
