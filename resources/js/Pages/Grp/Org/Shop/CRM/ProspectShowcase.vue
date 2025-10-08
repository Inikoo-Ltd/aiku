
<script setup lang="ts">
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLink } from "@far"
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
    faClock,
    faBuilding
} from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { inject, ref, computed } from "vue"
import Modal from "@/Components/Utils/Modal.vue"
import CustomerAddressManagementModal from "@/Components/Utils/CustomerAddressManagementModal.vue"
import { Address, AddressManagement } from "@/types/PureComponent/Address"
import Tag from "@/Components/Tag.vue"
import ModalRejected from "@/Components/Utils/ModalRejected.vue"
import ButtonPrimeVue from "primevue/button"
import ToggleSwitch from "primevue/toggleswitch"
import { Link, router } from "@inertiajs/vue3"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import CountUp from "vue-countup-v3"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import CustomerDSBalanceIncrease from "@/Components/Dropshipping/CustomerDSBalanceIncrease.vue"
import CustomerDSBalanceDecrease from "@/Components/Dropshipping/CustomerDSBalanceDecrease.vue"
import { faExclamationCircle, faCheckCircle, faCheck, faTimes } from '@fas'
import { faSpinnerThird } from '@fad'
import { Tooltip } from 'floating-vue'
import Button from "@/Components/Elements/Buttons/Button.vue"
import EmailSubscribetion from "@/Components/EmailSubscribetion.vue"
import ContactPreferences from "@/Components/ContactPreferences.vue"

library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faCheck, faPencil, faExclamationCircle, faCheckCircle, faSpinnerThird, faReceipt, faClock, faBuilding)

interface Customer {
    id: number
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
    balance: string
    tax_number?: {
        id: number
        number: string
        type: string
        country_id: number
        country: {
            code: string
            iso3: string
            name: string
        }
        status: string
        valid: boolean
        data: {
            name: string | null
            address: string | null
            exception?: {
                message: string
            }
        }
        checked_at: string
        invalid_checked_at: string | null
        external_service_failed_at: string | null
        validation_type: string | null
    }
    state: string
    status: string
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

interface ProspectData {
    slug: string
    name: string
    email: string
    phone: string | null
    contact_website: string | null
    address: any[]
    customer: { data: Customer }
    created_at: string
    updated_at: string
    state: string
    state_label: string
    contacted_state: string
    contacted_state_label: string
    fail_status: string
    fail_status_label: string
    success_status: string
    success_status_label: string
    dont_contact_me: boolean
    is_opt_in: boolean
    can_contact_by_email: boolean
    can_contact_by_phone: boolean
    can_contact_by_address: boolean
    last_contacted_at: string | null
    last_opened_at: string | null
    last_clicked_at: string | null
    dont_contact_me_at: string | null
    failed_at: string | null
    registered_at: string | null
    invoiced_at: string | null
    last_soft_bounced_at: string | null
}

const props = defineProps<{
    data: {
        prospect: ProspectData
    },
    tab: string
    handleTabUpdate?: Function
}>()

console.log(props.data);

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

const links = ref([
    {
        label: trans("Edit Web User"),
        route_target: props?.data?.editWebUser,
        icon: 'fal fa-pencil'
    },
]);


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

// Create contact preferences data from prospect data
const contactPreferencesData = computed(() => {
    if (!props.data?.prospect) return null
    
    return {
        update_route: {
            method: 'PATCH',
            name: 'prospect.contact-preferences.update',
            parameters: [props.data.prospect.slug]
        },
        dont_contact_me: {
            label: trans('Don\'t Contact Me'),
            is_active: props.data.prospect.dont_contact_me || false,
            activated_at: props.data.prospect.dont_contact_me_at,
            reason: null
        },
        preferences: {
            email: {
                label: trans('Email'),
                field: 'can_contact_by_email',
                is_allowed: props.data.prospect.can_contact_by_email || false,
                updated_at: null
            },
            phone: {
                label: trans('Phone Calls'),
                field: 'can_contact_by_phone',
                is_allowed: props.data.prospect.can_contact_by_phone || false,
                updated_at: null
            },
            address: {
                label: trans('Postal Mail'),
                field: 'can_contact_by_address',
                is_allowed: props.data.prospect.can_contact_by_address || false,
                updated_at: null
            }
        }
    }
})
</script>

<template>
    <!-- Section: Stats box -->
    <div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-2 gap-8">

        <!-- Section: Profile box -->
        <div>
            <div class="rounded-lg shadow-sm ring-1 ring-gray-900/5">
                <dl class="flex flex-wrap">


                    <!-- Section: Field -->
                    <div class="flex flex-col gap-y-3 border-t border-gray-900/5 w-full py-6">
                        <!-- Field: Contact name -->
                        <div v-if="data?.prospect?.customer?.data?.contact_name"
                            class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Contact name')" class="flex-none">
                                <span class="sr-only">Contact name</span>
                                <FontAwesomeIcon icon="fal fa-male" class="text-gray-400" fixed-width
                                    aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">{{ data?.prospect?.customer?.data?.contact_name }}</dd>
                        </div>

                        <!-- Field: Company name -->
                        <div v-if="data?.prospect?.customer?.data?.company_name"
                            class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Company name')" class="flex-none">
                                <span class="sr-only">Company name</span>
                                <FontAwesomeIcon icon="fal fa-building" class="text-gray-400" fixed-width
                                    aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">{{ data?.prospect?.customer?.data?.company_name }}</dd>
                        </div>

                        <!-- Field: Created at -->
                        <div v-if="data?.prospect?.created_at" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Created at')" class="flex-none">
                                <span class="sr-only">Created at</span>
                                <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" fixed-width
                                    aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">
                                <time>{{ useFormatTime(data?.prospect?.created_at) }}</time>
                            </dd>
                        </div>

                        <!-- Field: Email -->
                        <div v-if="data?.prospect?.customer?.data?.email"
                            class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Email')" class="flex-none">
                                <span class="sr-only">Email</span>
                                <FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width
                                    aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">
                                <a :href="`mailto:${data?.prospect?.customer?.data?.email}`">{{
                                    data?.prospect?.customer?.data?.email }}</a>
                            </dd>
                        </div>

                        <!-- Field: Phone -->
                        <div v-if="data?.prospect?.customer?.data?.phone"
                            class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Phone')" class="flex-none">
                                <span class="sr-only">Phone</span>
                                <FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width
                                    aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">
                                <a :href="`tel:${data?.prospect?.customer?.data?.phone}`">{{
                                    data?.prospect?.customer?.data?.phone }}</a>
                            </dd>
                        </div>

                        <!-- Field: Address -->
                        <div v-if="data?.prospect?.customer?.data?.address"
                            class="relative flex items w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="'Address'" class="flex-none">
                                <FontAwesomeIcon icon="fal fa-map-marker-alt" class="text-gray-400" fixed-width
                                    aria-hidden="true" />
                            </dt>
                            <dd class="w-full text-gray-500">
                                <div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
                                    <span class="" v-html="data?.prospect?.customer?.data?.address?.formatted_address" />

                                    <div v-if="data?.address_management?.can_open_address_management"
                                        @click="() => isModalAddress = true"
                                        class="w-fit pr-4 whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
                                        <span>{{ trans("Edit") }}</span>
                                    </div>
                                </div>
                            </dd>
                        </div>

                        <!-- Field: Last Contacted -->
                        <div v-if="data?.prospect?.last_contacted_at"
                            class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Last contacted')" class="flex-none">
                                <span class="sr-only">Last contacted</span>
                                <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width
                                    aria-hidden="true" />
                            </dt>
                            <dd class="text-gray-500">
                                <time>{{ useFormatTime(data?.prospect?.last_contacted_at) }}</time>
                            </dd>
                        </div>

                    </div>


                </dl>
            </div>
            <!-- Field: Tax Number -->
            <div v-if="data?.prospect?.customer?.tax_number && data?.prospect?.customer?.tax_number?.number"
                class="flex items-start w-full flex-none gap-x-4 px-6 mt-6">
                <dt v-tooltip="trans('Tax Number')" class="flex-none pt-1">
                    <span class="sr-only">Tax Number</span>
                    <FontAwesomeIcon icon="fal fa-receipt" class="text-gray-400" fixed-width aria-hidden="true" />
                </dt>
                <dd class="w-full text-gray-500">
                    <div class="space-y-2">
                        <!-- Tax Number Display -->
                        <div class="text-gray-900 font-medium">{{ data?.prospect?.customer?.tax_number?.number }}</div>

                        <!-- Validation Status Display -->
                        <div class="p-3 bg-gray-50 rounded-lg border">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center space-x-2">
                                    <FontAwesomeIcon
                                        :icon="getStatusIcon(data?.prospect?.customer?.tax_number?.status, data?.prospect?.customer?.tax_number?.valid)"
                                        :class="getStatusColor(data?.prospect?.customer?.tax_number?.status, data?.prospect?.customer?.tax_number?.valid)"
                                        class="text-sm" />


                                    <div class="space-y-2">
                                        <p class="text-sm text-gray-900">
                                            <span class="font-medium ">
                                                {{ getStatusText(data?.prospect?.customer?.tax_number?.status,
                                                data?.prospect?.customer?.tax_number?.valid) }}
                                            </span>
                                            <!-- Country -->
                                            <Tooltip v-if="data?.prospect?.customer?.tax_number?.country"
                                                class="inline ml-1">
                                                <div class="inline hover:underline cursor-default">({{
                                                    data?.prospect?.customer?.tax_number?.country?.name }})</div>

                                                <template #popper>
                                                    <div class="p-1 max-w-xs">
                                                        <div class="space-y-2">
                                                            <div class="text-sm space-y-1">
                                                                <p><span class="font-medium">{{ trans('Country')
                                                                        }}:</span> {{
                                                                    data?.prospect?.customer?.tax_number?.country?.name }}
                                                                </p>
                                                                <p><span class="font-medium">{{ trans('Country Code')
                                                                        }}:</span> {{
                                                                    data?.prospect?.customer?.tax_number?.country?.code }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </Tooltip>

                                            <!-- Last checked date -->
                                            <span v-if="data?.prospect?.customer?.tax_number?.checked_at"
                                                v-tooltip="trans('Last checked :date', { date: formatDate(data?.prospect?.customer?.tax_number?.checked_at) })"
                                                class="ml-1 cursor-default hover:underline">
                                                {{ formatDate(data?.prospect?.customer?.tax_number?.checked_at) }}
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


        <div class="justify-self-end ">

            <!-- Contact Preferences Section -->
            <ContactPreferences v-if="contactPreferencesData && layout?.app?.environment === 'local'"
                :contactPreferences="contactPreferencesData" />

            <!-- Email Subscriptions Section -->
            <!-- <EmailSubscribetion v-if="data?.prospect?.customer?.data?.email_subscriptions"
                :emailSubscriptions="data?.prospect?.customer?.data?.email_subscriptions" /> -->

        </div>
    </div>
</template>

<style scoped>

</style>
