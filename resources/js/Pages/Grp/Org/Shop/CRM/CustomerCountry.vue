<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { computed, ref } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableTopSoldProducts from "@/Components/Tables/Grp/Org/CRM/TableTopSoldProducts.vue"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUsers, faFileInvoice, faShoppingCart, faStar } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { PageHeadingTypes } from '@/types/PageHeading'

library.add(faUsers, faFileInvoice, faShoppingCart, faStar)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    country_code: string
    country_name: string
    stats: {
        number_customers: number
        number_invoices: number
        total_net_amount: number
        number_orders: number
        currency_code: string
    }
    top_products?: {}
    seasonal_products?: {}
}>()

const locale = useLocaleStore()
let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const kpiCards = computed(() => [
    {
        label: 'Customers',
        value: locale.number(props.stats.number_customers),
        icon: 'fal fa-users',
        color: '#6366f1',
    },
    {
        label: 'Invoices',
        value: locale.number(props.stats.number_invoices),
        icon: 'fal fa-file-invoice',
        color: '#0ea5e9',
    },
    {
        label: 'Revenue',
        value: locale.currencyFormat(props.stats.currency_code, props.stats.total_net_amount),
        icon: 'fal fa-star',
        color: '#10b981',
    },
    {
        label: 'Orders',
        value: locale.number(props.stats.number_orders),
        icon: 'fal fa-shopping-cart',
        color: '#f59e0b',
    },
])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :tabs="tabs.navigation" @tabChanged="handleTabUpdate" />

    <div v-if="currentTab === 'showcase'" class="mt-5 grid grid-cols-2 lg:grid-cols-4 gap-4 px-4">
        <div
            v-for="card in kpiCards"
            :key="card.label"
            class="bg-white rounded-lg border border-gray-200 shadow-sm px-5 py-5"
        >
            <div class="flex items-center gap-x-3">
                <div class="rounded-full p-2" :style="{ backgroundColor: card.color + '1a' }">
                    <FontAwesomeIcon :icon="card.icon" class="text-xl" :style="{ color: card.color }" fixed-width aria-hidden="true" />
                </div>
                <span class="text-sm font-medium text-gray-500">{{ card.label }}</span>
            </div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-gray-900 tabular-nums">
                {{ card.value }}
            </div>
        </div>
    </div>

    <TableTopSoldProducts v-else-if="currentTab === 'top_products'" :data="top_products" :tab="currentTab" />
    <TableTopSoldProducts v-else-if="currentTab === 'seasonal_products'" :data="seasonal_products" :tab="currentTab" />
</template>
