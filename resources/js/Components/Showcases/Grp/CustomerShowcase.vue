<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Thu, 25 May 2023 15:03:05 Central European Summer Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import {useFormatTime} from "@/Composables/useFormatTime"
import {routeType} from "@/types/route"
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome"
import {faLink} from "@far"
import {
    faSync,
    faCalendarAlt,
    faEnvelope,
    faPhone,
    faMapMarkerAlt,
    faMale,
    faPencil,
    faArrowAltFromTop,
    faArrowAltFromBottom,
    faReceipt,
    faCopy,
    faChartLine,
    faExclamationTriangle,
    faShoppingCart,
    faBoxOpen,
    faStickyNote,
    faClock,
    faListAlt,
} from "@fal"
import {library} from "@fortawesome/fontawesome-svg-core"
import {trans} from "laravel-vue-i18n"
import {inject, ref, computed} from "vue"
import Modal from "@/Components/Utils/Modal.vue"
import CustomerAddressManagementModal from "@/Components/Utils/CustomerAddressManagementModal.vue"
import {Address, AddressManagement} from "@/types/PureComponent/Address"
import Tag from "@/Components/Tag.vue"
import ModalRejected from "@/Components/Utils/ModalRejected.vue"
import ButtonPrimeVue from "primevue/button"
import ToggleSwitch from "primevue/toggleswitch"
import {Link, router, useForm} from "@inertiajs/vue3"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import CountUp from "vue-countup-v3"
import {aikuLocaleStructure} from "@/Composables/useLocaleStructure"
import CustomerDSBalanceIncrease from "@/Components/Dropshipping/CustomerDSBalanceIncrease.vue"
import CustomerDSBalanceDecrease from "@/Components/Dropshipping/CustomerDSBalanceDecrease.vue"
import { faExclamationCircle, faCheckCircle, faCheck, faTimes } from '@fas'
import { faSpinnerThird } from '@fad'
import { Tooltip } from 'floating-vue'
import Button from "@/Components/Elements/Buttons/Button.vue"
import EmailSubscribetion from "@/Components/EmailSubscribetion.vue"
import CustomerClv from "@/Components/CustomerCLV.vue"
import { notify } from "@kyvg/vue3-notification"
import CustomerSalesVsRefunds from "@/Components/CustomerSalesVsRefunds.vue";
import GoldReward from "@/Components/Utils/GoldReward.vue"
import CustomerMiniTimeline from "@/Components/Showcases/Grp/CustomerMiniTimeline.vue"

library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faCheck, faPencil, faExclamationCircle, faCheckCircle, faSpinnerThird, faReceipt, faCopy, faChartLine, faExclamationTriangle, faShoppingCart, faBoxOpen, faStickyNote, faClock, faListAlt)

interface Customer {
    slug: string
    reference: string
    name: string
    state: string
    phone: string
    status: string

    approved_at: string

    contact_name: string
    company_name: string
    location: string[]
    email: string

    created_at: string
    number_current_customer_clients: number | null
    address: Address
    is_dropshipping: boolean
    email_subscriptions?: {
        update_route: {
            method: string
            name: string
            parameters: number[]
        }
        suspended: {
            label: string
            is_suspended: boolean
            suspended_at: string | null
            suspended_cause: string | null
        }
        subscriptions: {
            [key: string]: {
                label: string
                field: string
                is_subscribed: boolean
                unsubscribed_at: string | null
            }
        }
    }
}

const props = defineProps<{
    data: {
        customer: Customer
        address_management: {
            can_open_address_management: boolean
            updateRoute: routeType
            addresses: AddressManagement
            address_update_route: routeType,
            address_modal_title: string
        }
        require_approval: boolean
        approveRoute: routeType
        editWebUser: routeType
        balance: {
            route_store: routeType
            route_increase: routeType
            route_decrease: routeType
            increase_reasons_options: {}[]
            decrease_reasons_options: {}[]
        }
        currency: {
            code: string
            symbol: string
        }
        type_options: {}
        tax_number: {}
        stats: any
        shop: {
            id: number
            type: string
            name: string
            slug: string
        }
        tag_routes: Record<string, routeType>
        tags: {}[]
        tags_selected_id: number[]
        note_route: routeType
        orders_route: routeType | null
        last_order: {
            reference: string
            slug: string
            state: string
            submitted_at: string | null
        } | null
    },
    gr_data: {
        gr_label: string
        meter: number[]
        customer_is_gr: boolean
    }
    tab: string
    handleTabUpdate?: Function
    timeline?: {
        events: {
            id: string
            type: string
            datetime: string
            title: string
            subtitle: string | null
            icon: string[]
            color: string
            metadata: Record<string, unknown>
        }[]
    }
}>()


const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout')

// console.log(layout);

const isModalAddress = ref(false)
const isModalUploadOpen = ref(false)

const visible = ref(false)

const customerID = ref()
const customerName = ref()

function openRejectedModal(customer: any) {
    customerID.value = customer.id
    customerName.value = customer.name
    isModalUploadOpen.value = true
}

const links = ref([])
if (props.data.editWebUser) {
    links.value.push({
        label: trans("Edit Web User"),
        route_target: props.data.editWebUser,
        icon: 'fal fa-pencil'
    })
}


// Section: Add Note
const isModalAddNote = ref(false)
const noteForm = useForm({ note: '' })
const submitNote = () => {
    noteForm.post(route(props.data.note_route.name, props.data.note_route.parameters), {
        onSuccess: () => {
            isModalAddNote.value = false
            noteForm.reset()
            notify({ title: trans('Note added'), type: 'success' })
        },
    })
}

// Section: Balance increase and decrease
const isModalBalanceDecrease = ref(false)
const isModalBalanceIncrease = ref(false)



// Tax number validation helper functions
const formatDate = (dateString: string | null) => {
    if (!dateString) return null

    try {
        return useFormatTime(dateString, {
            formatTime: 'dd MMM yyyy',
        })
    } catch (error) {
        console.error('Error formatting date:', error)
        return dateString
    }
}

const getStatusIcon = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return 'fa-exclamation-circle'
    }
    if (status === 'valid' || valid) {
        return 'fa-check-circle'
    }
    return 'fa-spinner-third'
}

const getStatusColor = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return 'text-red-600'
    }
    if (status === 'valid' || valid) {
        return 'text-green-600'
    }
    return 'text-yellow-600'
}

const getStatusText = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return trans('Invalid')
    }
    if (status === 'valid' || valid) {
        return trans('Valid')
    }
    return trans('Pending')
}

// Function: Copy to clipboard
const copyToClipboard = async (text: string, label: string) => {
    try {
        await navigator.clipboard.writeText(text)
        notify({
            title: trans("Copied!"),
            text: trans(`:label copied to clipboard`, { label: label }),
            type: "success"
        })
    } catch (error) {
        notify({
            title: trans("Failed"),
            text: trans("Failed to copy to clipboard"),
            type: "error"
        })
    }
}

const churnRiskLevel = computed(() => {
    const risk = props.data?.stats?.churn_risk_prediction ?? 0
    if (risk < 0.33) {
        return { label: trans('Low'), color: 'text-green-700', bg: 'bg-green-50', border: 'border-green-200', icon: 'text-green-400' }
    }
    if (risk < 0.66) {
        return { label: trans('Medium'), color: 'text-amber-700', bg: 'bg-amber-50', border: 'border-amber-200', icon: 'text-amber-400' }
    }
    return { label: trans('High'), color: 'text-red-700', bg: 'bg-red-50', border: 'border-red-200', icon: 'text-red-400' }
})

function tagColorClass(scope?: string) {
    const normalized = (scope || '').toLowerCase()

    switch (normalized) {
        case 'system customer':
            return 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100'
        case 'admin customer':
            return 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100'
        case 'user customer':
            return 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'
        default:
            return 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100'
    }
}
</script>

<template>
    <!-- Pending Approval Banner (full width) -->
    <div v-if="data.require_approval && data.customer.status === 'pending_approval'"
        class="px-4 py-4 md:px-6 lg:px-8">
        <div class="p-5 border rounded-lg bg-white">
            <div class="flex flex-col items-center text-center gap-2">
                <h3 class="text-lg font-semibold text-gray-800">Pending Application</h3>
                <p class="text-sm text-gray-600">
                    This application is currently awaiting approval.
                </p>
            </div>

            <div class="mt-5 flex justify-center gap-3">
                <Link :href="route(data.approveRoute.name, data.approveRoute.parameters)" method="patch"
                    :data="{ status: 'approved' }">
                <ButtonPrimeVue class="fixed-width-btn" severity="success" size="small" variant="outlined">
                    <FontAwesomeIcon :icon="faCheck" @click="visible = false" />
                    <span> Approve </span>
                </ButtonPrimeVue>
                </Link>

                <ButtonPrimeVue class="fixed-width-btn" severity="danger" size="small" variant="outlined"
                    @click="() => openRejectedModal(data.customer)">
                    <FontAwesomeIcon :icon="faTimes" @click="visible = false" />
                    <span> Reject </span>
                </ButtonPrimeVue>
            </div>
        </div>
    </div>

    <!-- Quick Actions Bar -->
    <div class="px-4 pt-6 md:px-6 lg:px-8 flex flex-wrap gap-3">
        <button
            @click="isModalAddNote = true"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
        >
            <FontAwesomeIcon icon="fal fa-sticky-note" class="text-amber-500 text-xs" />
            {{ trans('Add Note') }}
        </button>
        <Link
            v-if="data.orders_route"
            :href="route(data.orders_route.name, data.orders_route.parameters)"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
        >
            <FontAwesomeIcon icon="fal fa-list-alt" class="text-indigo-500 text-xs" />
            {{ trans('View Orders') }}
        </Link>
        <button
            v-if="handleTabUpdate"
            @click="() => handleTabUpdate('timeline')"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
        >
            <FontAwesomeIcon icon="fal fa-code-branch" class="text-green-500 text-xs" />
            {{ trans('Full Timeline') }}
        </button>
        <a
            v-if="data.customer.email"
            :href="`mailto:${data.customer.email}`"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
        >
            <FontAwesomeIcon icon="fal fa-envelope" class="text-blue-500 text-xs" />
            {{ trans('Send Email') }}
        </a>
    </div>

    <!-- 3-Column Hub Layout -->
    <div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT COLUMN: Profile -->
        <div class="lg:col-span-1">
            <div class="rounded-lg shadow-sm ring-1 ring-gray-900/5">
                <dl class="flex flex-wrap">
                    <div class="flex flex-col gap-y-3 border-t border-gray-900/5 w-full py-6">
                        <!-- Field: Gold reward member -->
                        <div v-if="gr_data.customer_is_gr" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt class="flex-none">
                                <FontAwesomeIcon icon="fas fa-medal" class="align-middle text-yellow-500" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="text-yellow-600 inline-flex items-center">
                                {{ gr_data?.gr_label }}
                                <GoldReward
                                    :label="gr_data?.gr_label"
                                    :meter="gr_data?.meter"
                                >
                                    <template #default>
                                        <div>
                                            <div class="ml-2 inline-block align-middle w-20 text-xxs rounded-sm h-3 my-1 bg-gray-200 relative overflow-hidden">
                                                <div class="bg-green-500 absolute left-0 top-0 h-full transition-all duration-1000 ease-in-out"
                                                    :style="{
                                                        width: true ? gr_data?.meter?.[0]/gr_data?.meter?.[1] * 100 + '%' : '100%'
                                                    }"
                                                />
                                                <div class="absolute inset-0 flex items-center justify-center text-xxs font-medium text-black">
                                                    {{ Number(gr_data?.meter?.[0]).toFixed(0) }} / {{ Number(gr_data?.meter?.[1]).toFixed(0) }} days
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </GoldReward>
                            </dd>
                        </div>

                        <!-- Field: Contact name -->
                        <div v-if="data?.customer?.contact_name"
                            class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Contact name')" class="flex-none">
                                <span class="sr-only">Contact name</span>
                                <FontAwesomeIcon icon="fal fa-male" class="text-gray-400" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">{{ data?.customer?.contact_name }}</dd>
                            <button @click="copyToClipboard(data?.customer?.contact_name, 'Contact name')"
                                class="text-gray-400 hover:text-gray-600 transition-colors"
                                v-tooltip="trans('Copy to clipboard')">
                                <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Field: Company name -->
                        <div v-if="data?.customer?.company_name"
                            class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Company name')" class="flex-none">
                                <span class="sr-only">Company name</span>
                                <FontAwesomeIcon icon="fal fa-building" class="text-gray-400" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">{{ data?.customer?.company_name }}</dd>
                            <button @click="copyToClipboard(data?.customer?.company_name, 'Company name')"
                                class="text-gray-400 hover:text-gray-600 transition-colors"
                                v-tooltip="trans('Copy to clipboard')">
                                <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Field: Created at -->
                        <div v-if="data?.customer?.created_at" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Created at')" class="flex-none">
                                <span class="sr-only">Created at</span>
                                <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">
                                <time datetime="2023-01-31">{{ useFormatTime(data?.customer?.created_at) }}</time>
                            </dd>
                            <button @click="copyToClipboard(useFormatTime(data?.customer?.created_at), 'Created at')"
                                class="text-gray-400 hover:text-gray-600 transition-colors"
                                v-tooltip="trans('Copy to clipboard')">
                                <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Field: Email -->
                        <div v-if="data?.customer?.email" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Email')" class="flex-none">
                                <span class="sr-only">Email</span>
                                <FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">
                                <a :href="`mailto:${data.customer.email}`">{{ data?.customer?.email }}</a>
                            </dd>
                            <button @click="copyToClipboard(data?.customer?.email, 'Email')"
                                class="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0"
                                v-tooltip="trans('Copy to clipboard')">
                                <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Field: Phone -->
                        <div v-if="data?.customer?.phone" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Phone')" class="flex-none">
                                <span class="sr-only">Phone</span>
                                <FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">
                                <a :href="`tel:${data.customer.phone}`">{{ data?.customer?.phone }}</a>
                            </dd>
                            <button @click="copyToClipboard(data?.customer?.phone, 'Phone')"
                                class="text-gray-400 hover:text-gray-600 transition-colors"
                                v-tooltip="trans('Copy to clipboard')">
                                <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Field: Address -->
                        <div v-if="data?.customer?.address"
                            class="relative flex items-start w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="'Address'" class="flex-none pt-2">
                                <FontAwesomeIcon icon="fal fa-map-marker-alt" class="text-gray-400" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="w-full text-gray-500">
                                <div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
                                    <span v-html="data?.customer?.address.formatted_address" />
                                    <div v-if="data.address_management.can_open_address_management && data.shop.type !== 'external'"
                                        @click="() => isModalAddress = true"
                                        class="w-fit pr-4 whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
                                        <span>{{ trans("Edit") }}</span>
                                    </div>
                                </div>
                            </dd>
                        </div>

                        <!-- Field: Tags -->
                        <div v-if="data.tags.length > 0" class="relative flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="'Tags'" class="flex-none pt-2">
                                <FontAwesomeIcon icon="fal fa-tags" class="text-gray-400" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="flex items-center gap-2 w-full flex-wrap">
                                <span
                                    v-for="tag in data.tags"
                                    :key="tag.id"
                                    v-tooltip="tag.scope"
                                    class="px-2 py-0.5 rounded-full text-xs font-medium border transition-colors duration-200 ease-in-out"
                                    :class="tagColorClass(tag.scope)"
                                >
                                    {{ tag.name }}
                                </span>
                            </dd>
                        </div>

                        <!-- Field: Last Order -->
                        <div v-if="data.last_order" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Last Order')" class="flex-none">
                                <FontAwesomeIcon icon="fal fa-shopping-cart" class="text-gray-400" fixed-width aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500 flex items-center gap-2 min-w-0">
                                <span class="font-medium text-gray-700">#{{ data.last_order.reference }}</span>
                                <span class="px-1.5 py-0.5 text-xs rounded bg-gray-100 text-gray-600 capitalize">
                                    {{ data.last_order.state.replace(/_/g, ' ') }}
                                </span>
                                <span v-if="data.last_order.submitted_at" class="text-xs text-gray-400 truncate">
                                    {{ useFormatTime(data.last_order.submitted_at, { formatTime: 'short-datetime' }) }}
                                </span>
                            </dd>
                        </div>
                    </div>
                </dl>
            </div>

            <!-- Field: Tax Number -->
            <div v-if="data?.customer.tax_number && data.customer.tax_number.number"
                class="flex items-start w-full flex-none gap-x-4 px-6 mt-6">
                <dt v-tooltip="trans('Tax Number')" class="flex-none pt-1">
                    <span class="sr-only">Tax Number</span>
                    <FontAwesomeIcon icon="fal fa-receipt" class="text-gray-400" fixed-width aria-hidden="true" />
                </dt>
                <dd class="w-full text-gray-500">
                    <div class="space-y-2">
                        <div class="flex items-center gap-x-2">
                            <div class="text-gray-900 font-medium">{{ data.customer.tax_number.number }}</div>
                            <button @click="copyToClipboard(data.customer.tax_number.number, 'Tax Number')"
                                class="text-gray-400 hover:text-gray-600 transition-colors"
                                v-tooltip="trans('Copy to clipboard')">
                                <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg border">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center space-x-2">
                                    <FontAwesomeIcon
                                        :icon="getStatusIcon(data.customer.tax_number.status, data.customer.tax_number.valid)"
                                        :class="getStatusColor(data.customer.tax_number.status, data.customer.tax_number.valid)"
                                        class="text-sm" />
                                    <div class="space-y-2">
                                        <p class="text-sm text-gray-900">
                                            <span class="font-medium">
                                                {{ getStatusText(data.customer.tax_number.status, data.customer.tax_number.valid) }}
                                            </span>
                                            <Tooltip v-if="data.customer.tax_number.country" class="inline ml-1">
                                                <div class="inline hover:underline cursor-default">({{ data.customer.tax_number.country.data.name }})</div>
                                                <template #popper>
                                                    <div class="p-1 max-w-xs">
                                                        <div class="space-y-2">
                                                            <div class="text-sm space-y-1">
                                                                <p><span class="font-medium">{{ trans('Country') }}:</span> {{ data.customer.tax_number.country.data.name }}</p>
                                                                <p><span class="font-medium">{{ trans('Country Code') }}:</span> {{ data.customer.tax_number.country.data.code }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </Tooltip>
                                            <span v-if="data.customer.tax_number.checked_at"
                                                v-tooltip="trans('Last checked :date', { date: formatDate(data.customer.tax_number.checked_at) })"
                                                class="ml-1 cursor-default hover:underline">
                                                {{ formatDate(data.customer.tax_number.checked_at) }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </dd>
            </div>
        </div>

        <!-- CENTER COLUMN: KPI Cards + Mini Timeline -->
        <div class="lg:col-span-1 flex flex-col gap-4">
            <!-- KPI Cards: 2x2 grid + last active -->
            <div v-if="data?.stats" class="grid grid-cols-2 gap-3">
                <!-- Card: Historic CLV -->
                <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                    <FontAwesomeIcon icon="fal fa-chart-line" class="text-lg text-indigo-400 flex-shrink-0" />
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">{{ trans('Lifetime Value') }}</div>
                        <div class="text-sm font-semibold text-gray-800 tabular-nums truncate">
                            <CountUp
                                :endVal="parseFloat(data.stats.historic_clv_amount ?? 0)"
                                :decimalPlaces="2"
                                :duration="1.5"
                                :scrollSpyOnce="true"
                                :options="{ formattingFn: (value) => locale.currencyFormat(data.currency?.code, value) }"
                            />
                        </div>
                    </div>
                </div>

                <!-- Card: Average Order Value -->
                <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                    <FontAwesomeIcon icon="fal fa-shopping-cart" class="text-lg text-blue-400 flex-shrink-0" />
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">{{ trans('Avg Order Value') }}</div>
                        <div class="text-sm font-semibold text-gray-800 tabular-nums truncate">
                            <CountUp
                                :endVal="parseFloat(data.stats.average_order_value ?? 0)"
                                :decimalPlaces="2"
                                :duration="1.5"
                                :scrollSpyOnce="true"
                                :options="{ formattingFn: (value) => locale.currencyFormat(data.currency?.code, value) }"
                            />
                        </div>
                    </div>
                </div>

                <!-- Card: Churn Risk -->
                <div class="flex items-center gap-3 rounded-lg border px-3 py-3"
                    :class="[churnRiskLevel.bg, churnRiskLevel.border]">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-lg flex-shrink-0" :class="churnRiskLevel.icon" />
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">{{ trans('Churn Risk') }}</div>
                        <div class="text-sm font-semibold tabular-nums" :class="churnRiskLevel.color">
                            {{ ((data.stats.churn_risk_prediction ?? 0) * 100).toFixed(0) }}%
                            <span class="text-xs font-normal ml-1">{{ churnRiskLevel.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- Card: Total Orders -->
                <component
                    :is="data.orders_route ? Link : 'div'"
                    v-bind="data.orders_route ? { href: route(data.orders_route.name, data.orders_route.parameters) } : {}"
                    class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3"
                    :class="{ 'hover:border-emerald-400 hover:bg-emerald-50 transition-colors cursor-pointer': data.orders_route }"
                >
                    <FontAwesomeIcon icon="fal fa-box-open" class="text-lg text-emerald-400 flex-shrink-0" />
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">{{ trans('Total Orders') }}</div>
                        <div class="text-sm font-semibold text-gray-800 tabular-nums">
                            <CountUp
                                :endVal="data.stats.number_orders ?? 0"
                                :decimalPlaces="0"
                                :duration="1.5"
                                :scrollSpyOnce="true"
                            />
                        </div>
                    </div>
                </component>

                <!-- Card: Last Active (full width) -->
                <div class="col-span-2 flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                    <FontAwesomeIcon icon="fal fa-clock" class="text-lg text-gray-400 flex-shrink-0" />
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">{{ trans('Last Active') }}</div>
                        <div class="text-sm font-semibold text-gray-800 truncate">
                            <span v-if="data.last_order?.submitted_at">
                                {{ useFormatTime(data.last_order.submitted_at, { formatTime: 'short-datetime' }) }}
                            </span>
                            <span v-else class="text-gray-400 font-normal">{{ trans('No activity') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mini Timeline -->
            <div class="rounded-lg border border-gray-200 p-4 flex flex-col gap-3">
                <h3 class="text-sm font-semibold text-gray-700">{{ trans('Recent Activity') }}</h3>
                <CustomerMiniTimeline
                    v-if="timeline?.events"
                    :events="timeline.events"
                    :on-view-all="handleTabUpdate ? () => handleTabUpdate('timeline') : undefined"
                />
                <div v-else class="space-y-3">
                    <div v-for="i in 5" :key="i" class="animate-pulse flex gap-3">
                        <div class="w-8 h-8 rounded-full bg-gray-200 shrink-0" />
                        <div class="flex-1 space-y-2 pt-1">
                            <div class="h-3 bg-gray-200 rounded w-3/4" />
                            <div class="h-2 bg-gray-100 rounded w-1/2" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Balance + Charts + Actions -->
        <div class="lg:col-span-1 flex flex-col gap-4">
            <!-- Balance Card -->
            <div v-if="data.shop.type !== 'external'"
                class="bg-indigo-50 border border-indigo-300 text-gray-700 flex flex-col justify-between px-4 py-5 sm:p-6 rounded-lg tabular-nums">
                <div class="w-full flex justify-between items-center">
                    <div>
                        <div class="text-base">
                            {{ trans("Balance") }}
                        </div>
                    </div>
                    <div class="flex flex-col items-end">
                        <div class="text-2xl font-bold">
                            <CountUp :endVal="data.customer.balance" :decimalPlaces="2" :duration="1.5"
                                :scrollSpyOnce="true" :options="{
                                    formattingFn: (value) =>
                                        locale.currencyFormat(data.currency?.code, value),
                                }" />
                        </div>
                        <div class="flex items-center">
                            <div @click="() => isModalBalanceIncrease = true"
                                v-tooltip="trans('Increase customer balance')"
                                class="cursor-pointer text-gray-400 hover:text-indigo-600">
                                <FontAwesomeIcon :icon="faArrowAltFromBottom" class="text-base"
                                    tooltip="Decrease Balance" fixed-width aria-hidden="true" />
                            </div>
                            <span class="mx-2 text-gray-400">|</span>
                            <div @click="() => isModalBalanceDecrease = true"
                                v-tooltip="trans('Decrease customer balance')"
                                class="cursor-pointer text-gray-400 hover:text-indigo-600">
                                <FontAwesomeIcon :icon="faArrowAltFromTop" class="text-base"
                                    tooltip="Decrease Balance" fixed-width aria-hidden="true" />
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="handleTabUpdate" @click="() => handleTabUpdate('credit_transactions')"
                    class="w-fit text-xs text-gray-400 hover:text-gray-700 mt-2 italic underline cursor-pointer">
                    {{ trans("See all transactions list") }}
                </div>
            </div>

            <!-- Charts -->
            <CustomerClv :data="data?.stats" :currencyCode="data.currency" />
            <CustomerSalesVsRefunds :data="data?.stats" :currency-code="data.currency" />

            <!-- Web User Links -->
            <div v-if="links.length" class="border border-gray-300 rounded-md p-2">
                <div v-for="(item, index) in links" :key="index" class="p-2">
                    <ButtonWithLink :routeTarget="item.route_target" full :icon="item.icon" :label="item.label"
                        type="secondary" />
                </div>
            </div>

            <!-- Email Subscriptions -->
            <EmailSubscribetion v-if="data?.customer?.email_subscriptions"
                :emailSubscriptions="data.customer.email_subscriptions" />
        </div>
    </div>

    <Modal v-if="data.address_management.can_open_address_management && data.shop.type !== 'external'" :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
        <CustomerAddressManagementModal :addresses="data.address_management.addresses"
            :updateRoute="data.address_management.address_update_route" />
    </Modal>

    <!-- Modal: Increase balance -->
    <Modal v-if="data.shop.type !== 'external'" :isOpen="isModalBalanceIncrease" @onClose="() => (isModalBalanceIncrease = false)" width="max-w-2xl w-full">
        <CustomerDSBalanceIncrease v-model="isModalBalanceIncrease" :routeSubmit="data.balance.route_increase"
            :options="data.balance.increase_reasons_options" :currency="data.currency"
            :types="data.balance.type_options" :balance="data.customer.balance" />
    </Modal>

    <!-- Modal: Decrease balance -->
    <Modal v-if="data.shop.type !== 'external'" :isOpen="isModalBalanceDecrease" @onClose="() => (isModalBalanceDecrease = false)" width="max-w-2xl w-full">
        <CustomerDSBalanceDecrease v-model="isModalBalanceDecrease" :routeSubmit="data.balance.route_decrease"
            :options="data.balance.decrease_reasons_options" :currency="data.currency"
            :types="data.balance.type_options" :balance="data.customer.balance" />
    </Modal>

    <ModalRejected v-model="isModalUploadOpen" :customerID="customerID" :customerName="customerName" />

    <!-- Modal: Add Note -->
    <Modal :isOpen="isModalAddNote" @onClose="() => { isModalAddNote = false; noteForm.reset() }" width="max-w-lg w-full">
        <div class="p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">{{ trans('Add Note') }}</h3>
            <textarea
                v-model="noteForm.note"
                rows="4"
                :placeholder="trans('Write a note about this customer...')"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-none"
            />
            <div v-if="noteForm.errors.note" class="mt-1 text-xs text-red-600">{{ noteForm.errors.note }}</div>
            <div class="mt-4 flex justify-end gap-2">
                <button
                    @click="() => { isModalAddNote = false; noteForm.reset() }"
                    class="px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                >
                    {{ trans('Cancel') }}
                </button>
                <button
                    @click="submitNote"
                    :disabled="noteForm.processing || !noteForm.note.trim()"
                    class="px-3 py-1.5 text-sm font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ noteForm.processing ? trans('Saving...') : trans('Save Note') }}
                </button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
/* Toggle Switch Active (Green) */
.toggle-switch-active :deep(.p-toggleswitch-slider) {
    background-color: #10b981 !important; /* green-500 */
}

.toggle-switch-active :deep(.p-toggleswitch-slider:hover) {
    background-color: #059669 !important; /* green-600 */
}

/* Toggle Switch Inactive (Red) */
.toggle-switch-inactive :deep(.p-toggleswitch-slider) {
    background-color: #ef4444 !important; /* red-500 */
}

.toggle-switch-inactive :deep(.p-toggleswitch-slider:hover) {
    background-color: #dc2626 !important; /* red-600 */
}

/* Handle (circle) styling */
.toggle-switch-active :deep(.p-toggleswitch-handle),
.toggle-switch-inactive :deep(.p-toggleswitch-handle) {
    background-color: white !important;
    border: 2px solid white !important;
}
</style>
