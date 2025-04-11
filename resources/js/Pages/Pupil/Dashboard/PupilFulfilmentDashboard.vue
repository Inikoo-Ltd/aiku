<!--
  - Author: Artha <artha@aw-advantage.com>
  - Created: Tue, 18 Mar 2025 12:01:24 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import TabSelector from '@/Components/Elements/TabSelector.vue'

import { computed, inject, ref } from 'vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import CustomerShowcaseStats from '@/Components/Showcases/Grp/CustomerShowcaseStats.vue'

import { routeType } from '@/types/route'
import { FulfilmentCustomerStats } from '@/types/Pallet'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { Link } from '@inertiajs/vue3'
import Tag from '@/Components/Tag.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Dialog from 'primevue/dialog';
import { get } from 'lodash'
import ButtonPrimeVue from 'primevue/button';


import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink, faLongArrowRight } from '@far'
import { faPencil, faWallet } from '@fal'
import { faSync, faCalendarAlt, faEnvelope, faPhone, faChevronRight, faExternalLink, faMapMarkerAlt, faAddressCard } from '@fal'
// import Modal from '@/Components/Utils/Modal.vue'
import { Address, AddressManagement } from '@/types/PureComponent/Address'
// import CustomerAddressManagementModal from '@/Components/Utils/CustomerAddressManagementModal.vue'
import CountUp from 'vue-countup-v3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import CustomerDataForm from '@/Components/CustomerDataForm.vue'
import { RuleType } from 'v-calendar/dist/types/src/utils/date/rules.js'
import { faCheck, faTimes } from '@fas'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
library.add(faWallet, faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faChevronRight, faExternalLink, faMapMarkerAlt, faAddressCard, faLongArrowRight, faCheck)

const props = defineProps<{
    shop: string,
    data: {
        addresses: AddressManagement
        address_update_route: routeType
        balance: {
            current: number
            credit_transactions: number
        }
        currency_code: string
        // customer: PalletCustomer
        fulfilment_customer: {
            radioTabs: {
                [key: string]: boolean
            }
            number_pallets?: number
            number_pallets_state_received?: number
            number_stored_items?: number
            number_pallets_deliveries?: number
            number_pallets_returns?: number
            customer: {
                address: Address
            }
        }
        updateRoute: routeType
        stats: {
            [key: string]: FulfilmentCustomerStats
        }
        warehouse_summary: {
            [key: string]: number
        }
        webhook: {
            webhook_access_key: string | null
            domain: string
            route: routeType
        }
        route_action: {
            route: routeType
            label: string
            style: string
            type: string
        }[]
        rental_agreement: {
            stats?: {
                data: {
                    id: number
                    slug: string
                    reference: string
                    state: string
                    billing_cycle: string
                    pallets_limit: number
                    route: routeType
                }
            }
            createRoute: routeType
            updated_at: string
        }
        recurring_bill: {
            route: routeType
            status: string  // 'former' and 'current'
            start_date: string
            end_date: string
            total: number
            currency_code: string
        }
        status: string
        additional_data: {
            product: String,
            size_and_weight: string,
            shipments_per_week: string
        },
        approveRoute: routeType
    }
}>()
// Mendapatkan data customer dari props
const customer = usePage().props?.layout?.customer;
const fulfilment = usePage().props?.layout?.fulfilment;

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', layoutStructure)

const optionRadio = [
    {
        value: 'pallets_storage',
        label: trans('Pallet Storage')
    },
    {
        value: 'items_storage',
        label: trans('Dropshipping')
    },
    /*    {
            value: 'dropshipping',
            label: trans('Dropshipping')
        },*/
]

const isLoading = ref<string | boolean>(false)
const tabs = {
    "pallets_storage": true,
    "items_storage": true,
    "dropshipping": false
}
const radioValue = ref<string[]>(Object.keys(props?.data?.fulfilment_customer?.radioTabs || tabs).filter(key => props?.data?.fulfilment_customer?.radioTabs[key] || tabs[key]))
const isLoadingButtonRentalAgreement = ref(false)

</script>

<template>
  <div class="p-8 pb-3 text-4xl font-bold">
    Welcome, {{ shop }}!
  </div>
     <!-- Section: Radiobox, Recurring bills balance, Rental agreement-->
     <div class="px-8 max-w-2xl gap-x-2 gap-y-8 lg:max-w-7xl pt-4">
     <div v-if="data?.status == 'approved'" class="w-full max-w-lg space-y-4">
        <div v-if="data?.balance?.current > 0"
            class="bg-indigo-50 border border-indigo-300 text-gray-700 flex flex-col justify-between px-4 py-5 sm:p-6 rounded-lg tabular-nums">
            <div class="w-full flex justify-between items-center">
                <div class="">
                    <div class="text-base capitalize">
                        {{ trans("balance") }}
                    </div>
                    <div class="text-xs text-gray-700/60">
                        {{ useFormatTime(new Date()) }}
                    </div>
                </div>

                <div class="rounded-md text-indigo-500/50 flex items-center justify-center">
                    <FontAwesomeIcon icon='fal fa-wallet' class='text-4xl' fixed-width aria-hidden='true' />
                </div>
            </div>

            <div
                class="mt-8 fflex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                <!-- In Total -->
                <div class="flex flex-col gap-y-1">
                    <CountUp :endVal="data?.balance?.current" :duration="1.5" :scrollSpyOnce="true" :options="{
                        formattingFn: (value: number) => locale.currencyFormat(data.currency_code, value)
                    }" />
                    <div class="text-gray-700/60 text-sm leading-4 font-normal">
                        {{ data?.balance?.credit_transactions }} credit transactions
                    </div>
                </div>
            </div>
        </div>

        <TabSelector :optionRadio="optionRadio" :radioValue="radioValue" :updateRoute="data.updateRoute" :disabled="true" />

        <div class="border-t border-gray-200 pt-4 w-full max-w-full">
            <!-- Section: Recurring Bills -->
            <div v-if="data?.recurring_bill"
                class="block group relative w-full gap-x-2 border border-gray-300 px-4 py-4 rounded-lg mb-4">
                <!-- <FontAwesomeIcon icon='fal fa-receipt' class='text-3xl text-gray-400' fixed-width aria-hidden='true' /> -->
                <div class="pl-2 leading-none text-lg" :style="{
                    borderLeft: `4px solid ${layout.app.theme[0]}`
                }">
                    <div class="block text-lg font-semibold">{{ trans("Current Bill") }}</div>
                </div>

                <!-- State Date & End Date -->
                <div class="pl-1 mt-4 w-80 lg:w-96 grid grid-cols-9 gap-x-3">
                    <div class="col-span-4 text-sm">
                        <div class="text-gray-400">{{ trans("Start date") }}</div>
                        <div class="font-medium">{{ useFormatTime(data?.recurring_bill?.start_date) }}</div>
                    </div>

                    <div class="flex justify-center items-center">
                        <FontAwesomeIcon icon='fal fa-chevron-right' class='text-xs' fixed-width
                            aria-hidden='true' />
                    </div>

                    <div class="col-span-4 text-sm">
                        <div class="text-gray-400">{{ trans("End date") }}</div>
                        <div class="font-medium">{{ useFormatTime(data?.recurring_bill?.end_date) }}</div>
                    </div>
                </div>

                <div class="pl-1 mt-6 w-full flex items-end justify-between">
                    <div class="flex h-fit">
                        <!-- <Tag :theme="data.recurring_bill.status === 'current' ? 3 : undefined" size="xxs">
                            <template #label>
                                <FontAwesomeIcon v-if="data.recurring_bill.status === 'current'"
                                    icon='fas fa-circle' class='text-green-500 animate-pulse text-[7px]' fixed-width
                                    aria-hidden='true' />
                                <span class="capitalize">{{ data.recurring_bill.status === 'current' ? 'Active' :
                                    'Past' }}</span>
                            </template>
                        </Tag> -->
                    </div>
                </div>
            </div>

            <!-- Section: Rental Agreement -->
            <div class="rounded-lg ring-1 ring-gray-300">
                <div class="border-b border-gray-300 py-2 px-2 pl-4 flex items-center justify-between">
                   <div class="">{{ trans('Rental Agreement') }} <span
                            v-if="data?.rental_agreement?.stats?.data?.reference" class="text-gray-400 text-sm">#{{
                                data?.rental_agreement?.stats?.data?.reference }}</span></div>

                </div>

                <!-- Stats -->
                <div v-if="data.rental_agreement.stats" class="p-5 space-y-2">
                    <div class="flex gap-x-1 items-center text-sm">
                        <div class="">{{ trans('Last updated') }}:</div>
                        <div class="text-gray-500">{{ useFormatTime(data?.rental_agreement?.updated_at) }}</div>
                    </div>
                    <div class="flex gap-x-1 items-center text-sm">
                        <div class="">{{ trans('Billing Cycle') }}:</div>
                        <div class="text-gray-500 capitalize">{{ data.rental_agreement.stats?.data.billing_cycle }}
                        </div>
                    </div>
                    <div class="flex gap-x-1 items-center text-sm">
                        <div class="">{{ trans('Pallet Limit') }}:</div>
                        <div class="text-gray-500">{{ data?.rental_agreement.stats?.data.pallets_limit ||
                            `(${trans('No limit')})` }}</div>
                    </div>
                </div>

                <div v-else class="text-center py-16">
                    <div class="text-gray-500 text-xs mb-1">The rental agreement is not created yet.</div>
                    <!-- <Link
                        :href="route(data.rental_agreement.createRoute.name, data.rental_agreement.createRoute.parameters)"
                        @start="() => isLoadingButtonRentalAgreement = true"
                        @cancel="() => isLoadingButtonRentalAgreement = false">
                    <Button type="secondary" label="Create Rental Agreement"
                        :loading="isLoadingButtonRentalAgreement" />
                    </Link> -->
                </div>
            </div>
        </div>
      </div>
    </div>
  <!-- Container untuk card -->
  <div v-if="customer?.status == 'pending_approval'" class="grid grid-cols-3 gap-6 p-6">
    <!-- Card Informasi Perusahaan -->
    <div class="col-span-3 bg-green-50 rounded-lg shadow-xl overflow-hidden border border-green-300 p-6">
      <h4 class="text-lg font-semibold text-green-800">{{ trans('Thank you for applying!')}}</h4>
      <p class="mt-2 text-sm text-green-700">{{trans('Your application is under review. Please wait for further information from us.') }}</p>
    </div>


    <div
      class="col-span-2 bg-white rounded-lg shadow-xl overflow-hidden border hover:shadow-2xl transition-shadow duration-300">
      <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-xl font-semibold text-gray-900">{{trans("My Details")}}</h3>
        <p class="mt-1 text-sm text-gray-500">{{trans("Company and contact information.")}}</p>
      </div>
      <div class="p-6 grid grid-cols-2 gap-4">
        <div>
          <h4 class="text-sm font-medium text-gray-500">{{trans("Company Name")}}</h4>
          <p class="mt-1 text-lg font-semibold text-gray-900">{{ customer.company_name }}</p>
        </div>
        <div>
          <h4 class="text-sm font-medium text-gray-500">{{trans("Contact Name")}}</h4>
          <p class="mt-1 text-lg font-semibold text-gray-900">{{ customer.contact_name }}</p>
        </div>
        <div>
          <h4 class="text-sm font-medium text-gray-500">{{trans("Email")}}</h4>
          <p class="mt-1 text-lg font-semibold text-gray-900">{{ customer.email }}</p>
        </div>
        <div>
          <h4 class="text-sm font-medium text-gray-500">{{trans('Phone')}}</h4>
          <p class="mt-1 text-lg font-semibold text-gray-900">{{ customer.phone }}</p>
        </div>
        <div class="col-span-2">
          <h4 class="text-sm font-medium text-gray-500">{{trans('Address')}}</h4>
          <p class="mt-1 text-sm text-gray-700" v-html="customer.address.formatted_address"></p>
        </div>
        <div>
          <h4 class="text-sm font-medium text-gray-500">{{trans("Status")}}</h4>
          <p class="mt-1 text-sm font-semibold" :class="{
            'text-green-700': customer.state === 'active',
            'text-red-700': customer.state !== 'active'
          }">
            {{ customer.state }}
          </p>
        </div>
      </div>
    </div>


    <div class="rounded-lg shadow-2xl overflow-hidden border border-[#0F1626] h-fit">
      <div class="px-6 py-4 border-b border-[#0F1626] bg-gradient-to-r from-gray-900 to-gray-800">
        <h4 class="text-2xl font-bold text-white">{{trans("Contact Us")}}</h4>
      </div>
      <div class="p-6 bg-white">
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-500">{{trans("Email")}}</h4>
          <p class="mt-2 text-lg font-semibold text-[#0F1626] hover:text-gray-500">
            <a :href="'mailto:' + 'info@aw-fulfilment.co.uk'" class="hover:underline">{{fulfilment.email}}</a>
          </p>
        </div>
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-500">{{trans("Phone")}}</h4>
          <p class="mt-2 text-lg font-semibold text-[#0F1626] hover:text-gray-500">
            {{fulfilment.phone}}
          </p>
        </div>
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-500">{{trans("Office Address")}}</h4>
          <div v-html="fulfilment?.address?.formatted_address" class="mt-2 text-lg font-semibold text-gray-900"/>

        </div>
      </div>
    </div>

  </div>
</template>
