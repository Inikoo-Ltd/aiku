<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 04 May 2025 16:27:31 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TablePlatformPortfolioLogs from "@/Components/Tables/Grp/Org/CRM/TablePlatformPortfolioLogs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from "laravel-vue-i18n"
import { computed, ref } from "vue"
import type { Component } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShopify, faTiktok } from "@fortawesome/free-brands-svg-icons"
import { faStore, faBookmark, faUndoAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { data } from "autoprefixer"
import CustomerSalesChannelShowcase from "@/Pages/Grp/Org/Dropshipping/CustomerSalesChannelShowcase.vue";

library.add(faStore, faBookmark, faUndoAlt)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    showcase: {
        stats: {
            name: string
            number_orders: number
            number_customer_clients: number
            number_portfolios: number
        }
        platform: {}
        customer_sales_channel: {}
        platform_user: {}
    }
    logs: {}
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        showcase: CustomerSalesChannelShowcase,
        logs: TablePlatformPortfolioLogs
    }

    return components[currentTab.value]
})

const isModalAddress = ref(false)
</script>

<template>
    <PageHeading :data="pageHead">
        <!-- <template #other>
            fffffffffff
            <ButtonWithLink label="Reset channel" type="red" xrouteTarget="{
                name: 'grp.models.customer_sales_channel.shopify_reset',

            }">

            </ButtonWithLink>
        </template> -->
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <component :is="component" :data="props[currentTab]" :tab="currentTab"/>
</template>
