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
    faReceipt
} from "@fal"
import {library} from "@fortawesome/fontawesome-svg-core"
import {trans} from "laravel-vue-i18n"
import {inject, ref} from "vue"
import Modal from "@/Components/Utils/Modal.vue"
import CustomerAddressManagementModal from "@/Components/Utils/CustomerAddressManagementModal.vue"
import {Address, AddressManagement} from "@/types/PureComponent/Address"
import Tag from "@/Components/Tag.vue"
import {faCheck, faTimes} from "@fas"
import ModalRejected from "@/Components/Utils/ModalRejected.vue"
import ButtonPrimeVue from "primevue/button"
import {Link} from "@inertiajs/vue3"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import CountUp from "vue-countup-v3"
import {aikuLocaleStructure} from "@/Composables/useLocaleStructure"
import CustomerDSBalanceIncrease from "@/Components/Dropshipping/CustomerDSBalanceIncrease.vue"
import CustomerDSBalanceDecrease from "@/Components/Dropshipping/CustomerDSBalanceDecrease.vue"
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import Popover from 'primevue/popover'

library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faCheck, faPencil, faExclamationCircle, faCheckCircle, faSpinnerThird, faReceipt)

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
            increaase_reasons_options: {}[]
            decrease_reasons_options: {}[]
        }
        currency: {
            code: string
            symbol: string
        }
        type_options: {}
        tax_number: {}
    },
    tab: string
    handleTabUpdate?: Function
}>()

console.log(props);

const locale = inject('locale', aikuLocaleStructure)

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

const links = ref([
    {
        label: trans("Edit Web User"),
        route_target: props.data.editWebUser,
        icon: 'fal fa-pencil'
    },
]);


// Section: Balance increase and decrease
const isModalBalanceDecrease = ref(false)
const isModalBalanceIncrease = ref(false)

// Popover refs for tax number
const statusPopover = ref()
const countryPopover = ref()
const datePopover = ref()

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
</script>

<template>
    <!-- Section: Stats box -->
    <div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-2 gap-8">
        <div v-if="data.require_approval && data.customer.status === 'pending_approval'"
             class="w-full max-w-md justify-self-end">
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
                            <FontAwesomeIcon :icon="faCheck" @click="visible = false"/>
                            <span> Approve </span>
                        </ButtonPrimeVue>
                    </Link>

                    <ButtonPrimeVue class="fixed-width-btn" severity="danger" size="small" variant="outlined"
                                    @click="() => openRejectedModal(data.customer)">
                        <FontAwesomeIcon :icon="faTimes" @click="visible = false"/>
                        <span> Reject </span>
                    </ButtonPrimeVue>
                </div>
            </div>
        </div>

        <!-- Section: Profile box -->
        <div>
            <div class="rounded-lg shadow-sm ring-1 ring-gray-900/5">
                <dl class="flex flex-wrap">
                    <!-- Profile: Header -->
                    <!-- <div class="flex w-full py-6">
                        <div v-if="data?.customer.is_dropshipping" class="flex-auto pl-6">
                            <dt class="text-sm text-gray-500">{{ trans("Total Clients") }}</dt>
                            <dd class="mt-1 text-base font-semibold leading-6">{{
                                data?.customer?.number_current_customer_clients || 0 }}</dd>
                        </div>

                        <div class="flex-none self-end px-6">
                            <dt class="sr-only">state</dt>
                            <dd class="capitalize">
                                <Tag :label="data?.customer?.state" :theme="data?.customer?.state === 'active'
                                    ? 3
                                    : data?.customer?.state === 'lost'
                                        ? 7
                                        : 99
                                    " />
                            </dd>
                        </div>
                    </div> -->

                    <!-- Section: Field -->
                    <div class="flex flex-col gap-y-3 border-t border-gray-900/5 w-full py-6">
                        <!-- Field: Contact name -->
                        <div v-if="data?.customer?.contact_name"
                             class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Contact name')" class="flex-none">
                                <span class="sr-only">Contact name</span>
                                <FontAwesomeIcon icon="fal fa-male" class="text-gray-400" fixed-width
                                                 aria-hidden="true"/>
                            </dt>
                            <dd class="text-gray-500">{{ data?.customer?.contact_name }}</dd>
                        </div>

                        <!-- Field: Contact name -->
                        <div v-if="data?.customer?.company_name"
                             class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Company name')" class="flex-none">
                                <span class="sr-only">Company name</span>
                                <FontAwesomeIcon icon="fal fa-building" class="text-gray-400" fixed-width
                                                 aria-hidden="true"/>
                            </dt>
                            <dd class="text-gray-500">{{ data?.customer?.company_name }}</dd>
                        </div>

                        <!-- Field: Created at -->
                        <div v-if="data?.customer?.created_at" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Created at')" class="flex-none">
                                <span class="sr-only">Created at</span>
                                <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" fixed-width
                                                 aria-hidden="true"/>
                            </dt>
                            <dd class="text-gray-500">
                                <time datetime="2023-01-31">{{ useFormatTime(data?.customer?.created_at) }}</time>
                            </dd>
                        </div>

                        <!-- Field: Email -->
                        <div v-if="data?.customer?.email" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Email')" class="flex-none">
                                <span class="sr-only">Email</span>
                                <FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width
                                                 aria-hidden="true"/>
                            </dt>
                            <dd class="text-gray-500">
                                <a :href="`mailto:${data.customer.email}`">{{ data?.customer?.email }}</a>
                            </dd>
                        </div>

                        <!-- Field: Phone -->
                        <div v-if="data?.customer?.phone" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Phone')" class="flex-none">
                                <span class="sr-only">Phone</span>
                                <FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width
                                                 aria-hidden="true"/>
                            </dt>
                            <dd class="text-gray-500">
                                <a :href="`tel:${data.customer.email}`">{{ data?.customer?.phone }}</a>
                            </dd>
                        </div>

                        <!-- Field: Address -->
                        <div v-if="data?.customer?.address" class="relative flex items w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="'Address'" class="flex-none">
                                <FontAwesomeIcon icon="fal fa-map-marker-alt" class="text-gray-400" fixed-width
                                                 aria-hidden="true"/>
                            </dt>
                            <dd class="w-full text-gray-500">
                                <div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
                                    <span class="" v-html="data?.customer?.address.formatted_address"/>

                                    <div v-if="data.address_management.can_open_address_management"
                                         @click="() => isModalAddress = true"
                                         class="w-fit pr-4 whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
                                        <span>{{ trans("Edit") }}</span>
                                    </div>
                                </div>
                            </dd>
                        </div>

                        <!-- Field: Tax Number -->
                        <div v-if="data?.tax_number && data.tax_number.number" class="flex items-start w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Tax Number')" class="flex-none pt-1">
                                <span class="sr-only">Tax Number</span>
                                <FontAwesomeIcon icon="fal fa-receipt" class="text-gray-400" fixed-width aria-hidden="true"/>
                            </dt>
                            <dd class="w-full text-gray-500">
                                <div class="space-y-2">
                                    <!-- Tax Number Display -->
                                    <div class="text-gray-900 font-medium">{{ data.tax_number.number }}</div>
                                    
                                    <!-- Validation Status Display -->
                                    <div class="p-3 bg-gray-50 rounded-lg border">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center space-x-2">
                                                <FontAwesomeIcon 
                                                    @click="statusPopover.toggle($event)"
                                                    :icon="getStatusIcon(data.tax_number.status, data.tax_number.valid)"
                                                    :class="getStatusColor(data.tax_number.status, data.tax_number.valid)" 
                                                    class="text-sm cursor-pointer" />
                                                
                                                <Popover ref="statusPopover">
                                                    <div class="p-4 max-w-xs">
                                                        <div class="space-y-2">
                                                            <div class="flex items-center space-x-2">
                                                                <FontAwesomeIcon 
                                                                    :icon="getStatusIcon(data.tax_number.status, data.tax_number.valid)"
                                                                    :class="getStatusColor(data.tax_number.status, data.tax_number.valid)" 
                                                                    class="text-sm" />
                                                                <span class="font-semibold text-sm">{{ trans('Validation Details') }}</span>
                                                            </div>
                                                            <div class="text-sm space-y-1">
                                                                <p><span class="font-medium">{{ trans('VAT Number') }}:</span> {{ data.tax_number.number }}</p>
                                                                <p><span class="font-medium">{{ trans('Status') }}:</span> 
                                                                    <span :class="getStatusColor(data.tax_number.status, data.tax_number.valid)">
                                                                        {{ getStatusText(data.tax_number.status, data.tax_number.valid) }}
                                                                    </span>
                                                                </p>
                                                                <p v-if="data.tax_number.country"><span class="font-medium">{{ trans('Country') }}:</span> {{ data.tax_number.country.name }} ({{ data.tax_number.country.code }})</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </Popover>

                                                <div class="space-y-2">
                                                    <p class="text-sm text-gray-900">
                                                        <span class="font-medium "
                                                            :class="getStatusColor(data.tax_number.status, data.tax_number.valid)">
                                                            {{ getStatusText(data.tax_number.status, data.tax_number.valid) }}
                                                        </span>
                                                        <span v-if="data.tax_number.country"
                                                            @click="countryPopover.toggle($event)"
                                                            class="cursor-pointer hover:underline"> 
                                                            ({{ data.tax_number.country.data.name }}) 
                                                        </span>
                                                        
                                                        <Popover ref="countryPopover">
                                                            <div class="p-4 max-w-xs">
                                                                <div class="space-y-2">
                                                                    <h4 class="font-semibold text-sm">{{ trans('Country Information') }}</h4>
                                                                    <div class="text-sm space-y-1">
                                                                        <p><span class="font-medium">{{ trans('Country') }}:</span> {{ data.tax_number.country.data.name }}</p>
                                                                        <p><span class="font-medium">{{ trans('Country Code') }}:</span> {{ data.tax_number.country.data.code }}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </Popover>

                                                        <span v-if="data.tax_number.checked_at"
                                                            @click="datePopover.toggle($event)"
                                                            class="cursor-pointer hover:underline">
                                                            {{ formatDate(data.tax_number.checked_at) }}
                                                        </span>
                                                        
                                                        <Popover ref="datePopover">
                                                            <div class="p-4 max-w-xs">
                                                                <div class="space-y-2">
                                                                    <div class="text-sm space-y-1">
                                                                        <p><span class="font-medium">{{ trans('Last checked') }}:</span> {{ formatDate(data.tax_number.checked_at) }}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </Popover>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </dd>
                        </div>
                    </div>


                </dl>
            </div>
            <!-- tax number info -->
        </div>


        <div class="justify-self-end ">
            <div
                class="bg-indigo-50 border border-indigo-300 text-gray-700 flex flex-col justify-between px-4 py-5 sm:p-6 rounded-lg tabular-nums">
                <div class="w-full flex justify-between items-center">
                    <div>
                        <div class="text-base capitalize">
                            {{ trans("balance") }}
                        </div>
                    </div>
                    <div class="flex flex-col items-end">
                        <div class="text-2xl font-bold">
                            <CountUp :endVal="data.customer.balance" :decimalPlaces="2" :duration="1.5"
                                     :scrollSpyOnce="true" :options="{
                                formattingFn: (value) =>
                                    locale.currencyFormat(data.currency?.code, value),
                            }"/>
                        </div>
                        <div class="flex items-center">
                            <div @click="() => isModalBalanceIncrease = true"
                                 v-tooltip="trans('Increase customer balance')"
                                 class="cursor-pointer text-gray-400 hover:text-indigo-600">
                                <FontAwesomeIcon
                                    :icon="faArrowAltFromBottom"
                                    class="text-base"
                                    tooltip="Decrease Balance"
                                    fixed-width
                                    aria-hidden="true"/>
                            </div>
                            <span class="mx-2 text-gray-400">|</span>
                            <div @click="() => isModalBalanceDecrease = true"
                                 v-tooltip="trans('Decrease customer balance')"
                                 class="cursor-pointer text-gray-400 hover:text-indigo-600">
                                <FontAwesomeIcon
                                    :icon="faArrowAltFromTop"
                                    class="text-base"
                                    tooltip="Decrease Balance"
                                    fixed-width
                                    aria-hidden="true"/>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="handleTabUpdate" @click="() => handleTabUpdate('credit_transactions')"
                     class="w-fit text-xs text-gray-400 hover:text-gray-700 mt-2 italic underline cursor-pointer">
                    {{ trans("See all transactions list") }}
                </div>
            </div>

            <div class="mt-4 w-64 border border-gray-300 rounded-md p-2">
                <div v-for="(item, index) in links" :key="index" class="p-2">
                    <ButtonWithLink :routeTarget="item.route_target" full :icon="item.icon" :label="item.label"
                                    type="secondary"/>
                </div>
            </div>
        </div>
    </div>

    <Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
        <CustomerAddressManagementModal :addresses="data.address_management.addresses"
                                        :updateRoute="data.address_management.address_update_route"/>
    </Modal>

    <!-- Modal: Increase balance -->
    <Modal :isOpen="isModalBalanceIncrease" @onClose="() => (isModalBalanceIncrease = false)" width="max-w-2xl w-full">
        <CustomerDSBalanceIncrease
            v-model="isModalBalanceIncrease"
            :routeSubmit="data.balance.route_increase"
            :options="data.balance.increaase_reasons_options"
            :currency="data.currency"
            :types="data.balance.type_options"
        />
    </Modal>

    <!-- Modal: Decrease balance -->
    <Modal :isOpen="isModalBalanceDecrease" @onClose="() => (isModalBalanceDecrease = false)" width="max-w-2xl w-full">
        <CustomerDSBalanceDecrease
            v-model="isModalBalanceDecrease"
            :routeSubmit="data.balance.route_decrease"
            :options="data.balance.decrease_reasons_options"
            :currency="data.currency"
            :types="data.balance.type_options"
        />
    </Modal>

    <ModalRejected v-model="isModalUploadOpen" :customerID="customerID" :customerName="customerName"/>
</template>
