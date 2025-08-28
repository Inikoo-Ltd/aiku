<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faCoins, faUndo
} from '@fal';

import PageHeading from '@/Components/Headings/PageHeading.vue';
import {computed, defineAsyncComponent, ref} from "vue";
import {useTabChange} from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import {capitalize} from "@/Composables/capitalize"
import PaymentShowcase from './PaymentShowcase.vue';
// import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
import RefundModal from '@/Components/RefundModal.vue';
import Button from '@/Components/Elements/Buttons/Button.vue';
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue";


library.add(faCoins, faUndo);

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

interface Country {
    code: string
    iso3: string
    name: string
}

interface Address {
    id: number
    address_line_1: string
    address_line_2: string
    sorting_code: string
    postal_code: string
    locality: string
    dependent_locality: string
    administrative_area: string
    country_code: string
    country_id: number
    checksum: string
    created_at: string
    updated_at: string
    country: Country
    formatted_address: string
    can_edit: any
    can_delete: any
}

interface CustomerData {
    slug: string
    reference: string
    name: string
    contact_name: string
    company_name: string
    location: string[]
    address: Address
    email: string
    phone: string
    created_at: string
    number_current_customer_clients: number | null
    state?: string
    is_dropshipping?: boolean
}

interface ParentData {
    id: number
    reference: string
    slug: string
    state: string
    state_label: string
    state_icon: {
        tooltip: string
        icon: string
        class: string
        color: string
        app: {
            name: string
            type: string
        }
    }
    public_notes: string | null
    customer_name: string
    customer_slug: string
    currency_code: string
    net_amount: string
    customer_notes: string | null
    shipping_notes: string | null
    payment_amount: string
    total_amount: string
    is_fully_paid: boolean
    unpaid_amount: number
    created_at: string
    updated_at: string
    submitted_at: string
    in_warehouse_at: string
    handling_at: string
    packed_at: string | null
    finalised_at: string | null
    dispatched_at: string | null
    cancelled_at: string | null
}

interface CurrencyData {
    id: number
    code: string
    name: string
    symbol: string
}

interface PaymentAccountData {
    slug: string
    name: string
    number_payments: number
    code: string
    created_at: string
    updated_at: string
}

interface PaymentServiceProviderData {
    slug: string
    code: string
    name: string
    created_at: string
}

interface CreditTransactionData {
    id: number
    payment_id: number
    type: string
    amount: string
    running_amount: string
    payment_reference: string | null
    payment_type: string | null
    currency_code: string | null
    created_at: string
}

interface Showcase {
    parent_type: string | null
    amount: string
    state: string
    customer: { data: CustomerData }
    parent_data: { data: ParentData } | null
    currency: { data: CurrencyData }
    paymentAccount: { data: PaymentAccountData }
    paymentServiceProvider: { data: PaymentServiceProviderData }
    credit_transaction: { data: CreditTransactionData } | null
}

interface Props {
    title: string
    pageHead: any
    tabs: {
        current: string
        navigation: Record<string, any>
    }
    showcase: Showcase
    refunds?: {}
    refund_route?: {
        name: string
        parameters: {
            organisation: number
            payment: number
        }
    }
}

const props = defineProps<Props>()

// Refund modal state
const showRefundModal = ref(false)

// Computed properties for refund conditions
const isRefund = computed(() => {
    return parseFloat(props?.showcase?.amount) < 0
})

const canRefund = computed(() => {
    // Only allow refund for completed payments, not already refunds, and must have refund route
    return props.showcase.state === 'completed' &&
        !isRefund.value &&
        parseFloat(props.showcase.amount) > 0 &&
        props.refund_route
})

const showRefundButton = computed(() => {
    // Show refund button only in showcase tab and when conditions are met
    return props.tabs.current === 'showcase' && canRefund.value
})

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        details: ModelDetails,
        history: ModelChangelog,
        showcase: PaymentShowcase,
        refunds: TablePayments
    };
    return components[currentTab.value];

});


// Methods
const openRefundModal = () => {
    showRefundModal.value = true
}

const closeRefundModal = () => {
    showRefundModal.value = false
}

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"><template #other>
            <Button v-if="showRefundButton" @click="openRefundModal" :icon="faUndo" label="Proceed Refund">

            </Button>
        </template></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" @open-refund-modal="openRefundModal">
    </component>
    <RefundModal :showcase="showcase" :refund-route="refund_route" :is-visible="showRefundModal"
        @close="closeRefundModal" />
</template>

