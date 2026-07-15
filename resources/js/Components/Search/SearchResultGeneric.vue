<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faShoppingCart, faFileInvoiceDollar, faCoins, faTruck, faBox, faBoxesAlt, faAtom,
    faPersonDolly, faUserPlus, faStar, faChargingStation, faConciergeBell, faShippingFast,
    faMapMarkedAlt, faBadgePercent, faCommentDollar, faMailBulk, faBrowser, faCube,
    faFolderTree, faAlbumCollection, faCopyright, faTags, faBarcode, faEnvelope, faPhone,
    faSpinnerThird, faUserHardHat,
} from '@fal'
import { Link } from '@inertiajs/vue3'
import Skeleton from 'primevue/skeleton'
import { computed, ref, watch } from 'vue'

library.add(
    faShoppingCart, faFileInvoiceDollar, faCoins, faTruck, faBox, faBoxesAlt, faAtom,
    faPersonDolly, faUserPlus, faStar, faChargingStation, faConciergeBell, faShippingFast,
    faMapMarkedAlt, faBadgePercent, faCommentDollar, faMailBulk, faBrowser, faCube,
    faFolderTree, faAlbumCollection, faCopyright, faTags, faBarcode, faEnvelope, faPhone,
    faSpinnerThird, faUserHardHat,
)

type ResultItem = {
    id: number
    slug?: string
    code?: string
    name?: string
    state?: string
    contact_name?: string
    company_name?: string
    email?: string
    phone?: string
    rating?: number
}

type SectionConfig = {
    label: string
    icon: string
    redirectRoute?: string
}

const SECTIONS: Record<string, SectionConfig> = {
    orders: { label: 'Orders', icon: 'fal fa-shopping-cart', redirectRoute: 'grp.majordomo.redirect_order' },
    invoices: { label: 'Invoices', icon: 'fal fa-file-invoice-dollar', redirectRoute: 'grp.majordomo.redirect_invoice_in_accounting' },
    payments: { label: 'Payments', icon: 'fal fa-coins' },
    delivery_notes: { label: 'Delivery Notes', icon: 'fal fa-truck', redirectRoute: 'grp.majordomo.redirect_delivery_notes' },
    stocks: { label: 'Stocks', icon: 'fal fa-box', redirectRoute: 'grp.majordomo.redirect_stock' },
    stock_families: { label: 'Stock Families', icon: 'fal fa-boxes-alt', redirectRoute: 'grp.majordomo.redirect_stock_family' },
    trade_units: { label: 'Trade Units', icon: 'fal fa-atom', redirectRoute: 'grp.majordomo.redirect_trade_unit' },
    trade_unit_families: { label: 'Trade Unit Families', icon: 'fal fa-boxes-alt', redirectRoute: 'grp.majordomo.redirect_trade_unit_family' },
    suppliers: { label: 'Suppliers', icon: 'fal fa-person-dolly', redirectRoute: 'grp.majordomo.redirect_supplier' },
    prospects: { label: 'Prospects', icon: 'fal fa-user-plus', redirectRoute: 'grp.majordomo.redirect_prospect' },
    reviews: { label: 'Reviews', icon: 'fal fa-star' },
    charges: { label: 'Charges', icon: 'fal fa-charging-station', redirectRoute: 'grp.majordomo.redirect_charge' },
    services: { label: 'Services', icon: 'fal fa-concierge-bell', redirectRoute: 'grp.majordomo.redirect_service' },
    shipping_zone_schemas: { label: 'Shipping Schemas', icon: 'fal fa-shipping-fast', redirectRoute: 'grp.majordomo.redirect_shipping_zone_schema' },
    shipping_zones: { label: 'Shipping Zones', icon: 'fal fa-map-marked-alt' },
    offers: { label: 'Offers', icon: 'fal fa-badge-percent', redirectRoute: 'grp.majordomo.redirect_offer' },
    offer_campaigns: { label: 'Campaigns', icon: 'fal fa-comment-dollar', redirectRoute: 'grp.majordomo.redirect_offer_campaign' },
    mailshots: { label: 'Mailshots', icon: 'fal fa-mail-bulk', redirectRoute: 'grp.majordomo.redirect_mailshot' },
    webpages: { label: 'Webpages', icon: 'fal fa-browser', redirectRoute: 'grp.majordomo.redirect_webpage' },
    master_products: { label: 'Master Products', icon: 'fal fa-cube', redirectRoute: 'grp.majordomo.redirect_master_product' },
    master_product_categories: { label: 'Master Categories', icon: 'fal fa-folder-tree', redirectRoute: 'grp.majordomo.redirect_master_product_category' },
    master_collections: { label: 'Master Collections', icon: 'fal fa-album-collection', redirectRoute: 'grp.majordomo.redirect_master_collection' },
    brands: { label: 'Brands', icon: 'fal fa-copyright', redirectRoute: 'grp.majordomo.redirect_brand' },
    tags: { label: 'Tags', icon: 'fal fa-tags' },
    barcodes: { label: 'Barcodes', icon: 'fal fa-barcode', redirectRoute: 'grp.majordomo.redirect_barcode' },
    employees: { label: 'Employees', icon: 'fal fa-user-hard-hat', redirectRoute: 'grp.majordomo.redirect_employee' },
}

const model = defineModel('open')

const props = defineProps<{
    results: Record<string, ResultItem[]> | null
    isLoading: boolean
    query: string
}>()

const tabs = computed(() =>
    Object.keys(props.results ?? {}).filter((key) => SECTIONS[key])
)

const activeTab = ref<string>(tabs.value[0] ?? '')
watch(tabs, (newTabs) => {
    if (!newTabs.includes(activeTab.value)) {
        activeTab.value = newTabs[0] ?? ''
    }
})

const loadingId = ref<number | null>(null)

function buildHref(sectionKey: string, item: ResultItem): string | null {
    const redirectRoute = SECTIONS[sectionKey]?.redirectRoute

    return redirectRoute ? route(redirectRoute, [item.id]) : null
}

const activeItems = computed(() =>
    (props.results?.[activeTab.value] ?? []).map((item) => ({
        ...item,
        href: buildHref(activeTab.value, item),
    }))
)
</script>

<template>
    <div class="col-span-3 border-r p-4 bg-gray-50">
        <div v-if="isLoading" class="space-y-2">
            <Skeleton height="2.5rem" borderRadius="0.375rem" />
            <Skeleton height="2.5rem" borderRadius="0.375rem" />
        </div>
        <div v-else class="space-y-2">
            <button
                v-for="tab in tabs"
                :key="tab"
                type="button"
                class="w-full p-3 rounded-md text-sm flex items-center justify-between transition active:scale-[0.98]"
                :class="activeTab === tab
                    ? 'bg-white shadow-sm ring-1 ring-slate-200 text-slate-900'
                    : 'bg-white/60 text-slate-600 hover:bg-slate-100'"
                @click="activeTab = tab"
            >
                <span class="font-medium text-left">
                    <FontAwesomeIcon :icon="SECTIONS[tab].icon" fixed-width aria-hidden="true" />
                    {{ ctrans(SECTIONS[tab].label) }}
                </span>
                <span class="text-xs text-gray-400">{{ results?.[tab]?.length ?? 0 }}</span>
            </button>
        </div>
    </div>

    <div class="col-span-9 flex flex-col min-h-0">
        <div class="flex-1 p-4 space-y-4 overflow-y-auto">
            <div v-if="isLoading" class="space-y-4">
                <div v-for="i in 6" :key="i" class="p-4 rounded-md border bg-white">
                    <div class="flex justify-between items-center mb-2">
                        <Skeleton width="60%" height="1rem" />
                        <Skeleton width="60px" height="0.75rem" borderRadius="999px" />
                    </div>
                    <Skeleton width="40%" height="0.75rem" />
                </div>
            </div>

            <div v-else-if="activeItems.length">
                <component
                    :is="item.href ? Link : 'div'"
                    v-for="item in activeItems"
                    :key="item.id"
                    :href="item.href ?? undefined"
                    class="block p-4 rounded-md border border-transparent bg-slate-50 hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm mb-3"
                    :class="item.href ? 'cursor-pointer' : 'cursor-default'"
                    @start="() => { model = false; loadingId = item.id }"
                    @finish="() => loadingId = null"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold truncate min-w-0">
                            {{ item.code || item.name || item.contact_name || item.company_name }}
                        </p>
                        <span v-if="item.rating != null" class="shrink-0 text-xs text-amber-500">
                            <FontAwesomeIcon icon='fal fa-star' fixed-width aria-hidden='true' />{{ item.rating }}
                        </span>
                        <span v-if="loadingId === item.id" class="shrink-0 text-slate-400">
                            <FontAwesomeIcon icon='fal fa-spinner-third' spin fixed-width aria-hidden='true' />
                        </span>
                        <span
                            v-else-if="item.state"
                            class="shrink-0 text-[10px] px-2 py-0.5 rounded-full capitalize"
                            :class="item.state === 'active' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ item.state.replaceAll('_', ' ') }}
                        </span>
                    </div>

                    <p v-if="item.code && (item.name || item.contact_name)" class="text-xs text-gray-400 mt-2 truncate">
                        {{ item.name || item.contact_name }}
                        <span v-if="item.company_name && item.company_name !== (item.name || item.contact_name)" class="italic">
                            — {{ item.company_name }}
                        </span>
                    </p>

                    <div v-if="item.email || item.phone" class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                        <span v-if="item.email" class="inline-flex items-center gap-1 min-w-0 max-w-full truncate">
                            <FontAwesomeIcon icon='fal fa-envelope' fixed-width aria-hidden='true' />
                            <span class="truncate">{{ item.email }}</span>
                        </span>
                        <span v-if="item.phone" class="inline-flex items-center gap-1">
                            <FontAwesomeIcon icon='fal fa-phone' fixed-width aria-hidden='true' />
                            {{ item.phone }}
                        </span>
                    </div>
                </component>
            </div>

            <div v-else class="flex h-full items-center justify-center text-gray-400 text-sm">
                {{ ctrans("No results") }}
            </div>
        </div>
    </div>
</template>
