<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
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
import { faCommentDollar, faInfoCircle, faTags } from '@fal'
import ModalCreateCategoryOffers from '@/Components/Offers/ModalCreateCategoryOffers.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
library.add( faCommentDollar, faInfoCircle, faTags )


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
        slug: string
        currency_code: string
        organisation: string
        offercampaign: string
    }
    tabsBox?: {
        label: string
        value: string | number
        indicator?: boolean
        tab_slug: string
        type?: string // 'icon', 'date', 'number', 'currency'
        align?: string
        icon?: string | string[]
        iconClass?: string
        tooltip?: string
        information?: {
            label: string | number
            type?: string // 'icon', 'date', 'number', 'currency'
        }
        visitRoute?: {
            name: string
            parameters: {}
        }
    }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        overview: CampaignOverview,
        offers: TableOffers,
        history: TableHistories
    }

    return components[currentTab.value]
})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <ModalCreateCategoryOffers
                :shop_data="props.shop_data"
            />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" :tabsBox="tabsBox" />

</template>
