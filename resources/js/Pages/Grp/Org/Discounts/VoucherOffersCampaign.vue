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
import { faCommentDollar, faInfoCircle, faStore } from '@fal'
import ModalCreateVoucherOffers from '@/Components/Offers/ModalCreateVoucherOffers.vue'
import ModalCreateCustomerListVoucher from '@/Components/Offers/ModalCreateCustomerListVoucher.vue'
import CustomerListVouchers from '@/Components/Offers/CustomerListVouchers.vue'

library.add(faCommentDollar, faInfoCircle, faStore)


const props = defineProps<{
    can_edit?: boolean
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    offers?: {}
    overview?: {
        offerCampaign: {}
        stats: {}
    }
    shop_data: {
        id: number
        slug: string
        currency_code: string
        organisation: string
        offercampaign: string
    }
    customer_list_vouchers: {
        vouchers: []
        can_edit: boolean
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
            <div class="flex gap-x-2">
                <ModalCreateCustomerListVoucher v-if="can_edit" :shop_data="props.shop_data" />
                <ModalCreateVoucherOffers v-if="can_edit" :shop_data="props.shop_data" />
            </div>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
    <CustomerListVouchers
        v-if="currentTab === 'overview'"
        :vouchers="customer_list_vouchers.vouchers"
        :canEdit="customer_list_vouchers.can_edit"
        :currencyCode="shop_data.currency_code"
    />
</template>
