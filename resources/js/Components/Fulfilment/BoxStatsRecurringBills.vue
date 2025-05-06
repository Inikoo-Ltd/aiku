<script setup lang='ts'>
import { inject} from 'vue'
import { BoxStats } from '@/types/Pallet'
import { capitalize } from '@/Composables/capitalize'
import { trans } from 'laravel-vue-i18n'
import BoxStatPallet from '@/Components/Pallet/BoxStatPallet.vue'
import { Link } from '@inertiajs/vue3'


import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faQuestionCircle, faIdCardAlt, faEnvelope, faPhone, faCalendarDay, faConciergeBell , faBuilding} from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import OrderSummary from '@/Components/Summary/OrderSummary.vue'

library.add(faQuestionCircle, faIdCardAlt, faEnvelope, faPhone, faCalendarDay, faConciergeBell, faBuilding)

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', null)
const props = defineProps<{
    boxStats: BoxStats
    currency?: object
}>()


</script>

<template>
    <div class="h-min grid grid-cols-2 sm:grid-cols-4 border-t border-b border-gray-200 divide-x divide-gray-300">
        <!-- Box: Customer -->
        <BoxStatPallet class="py-1 sm:py-2 px-4" :label="'dataPalletDelivery.customer_name'" icon="fal fa-user">
            
            <div class="space-y-1">
                <div v-if="boxStats.customer.data.customer.contact_name && layout?.app?.name != 'retina'"
                    class="flex items-center w-full flex-none gap-x-2">
                    <dt v-tooltip="'Contact name'" class="flex-none">
                        <span class="sr-only">Contact name</span>
                        <FontAwesomeIcon icon='fal fa-user' size="xs" class='pl-1 text-gray-400' fixed-width
                            aria-hidden='true' />
                    </dt>
                    <Link :href="route('grp.org.fulfilments.show.crm.customers.show', [route().params.organisation, route().params.fulfilment, boxStats.customer.data.slug ])" class="text-base text-gray-500 secondaryLink">{{ boxStats.customer.data.customer.contact_name }}</Link>
                </div>

                <!-- Field: Company name -->
                <div v-if="boxStats.customer.data.customer.company_name"
                    class="flex items-center w-full flex-none gap-x-2">
                    <dt v-tooltip="'Company name'" class="flex-none">
                        <span class="sr-only">Company name</span>
                        <FontAwesomeIcon icon='fal fa-building' size="xs" class='pl-1 text-gray-400' fixed-width
                            aria-hidden='true' />
                    </dt>
                    <dd class="text-base text-gray-500">{{ boxStats.customer.data.customer.company_name }}</dd>
                </div>

                <!-- Field: Email -->
                <div v-if="boxStats.customer.data?.customer.email" class="flex items-center w-full flex-none gap-x-2">
                    <dt v-tooltip="'Email'" class="flex-none">
                        <span class="sr-only">Email</span>
                        <FontAwesomeIcon icon='fal fa-envelope' size="xs" class='pl-1 text-gray-400' fixed-width
                            aria-hidden='true' />
                    </dt>
                    <dd class="text-base text-gray-500 white w-full truncate">{{ boxStats.customer.data?.customer.email }}</dd>
                </div>

                <!-- Field: Phone -->
                <div v-if="boxStats.customer.data?.customer.phone" class="flex items-center w-full flex-none gap-x-2">
                    <dt v-tooltip="'Phone'" class="flex-none">
                        <span class="sr-only">Phone</span>
                        <FontAwesomeIcon icon='fal fa-phone' size="xs" class='pl-1 text-gray-400' fixed-width
                            aria-hidden='true' />
                    </dt>
                    <dd class="text-base text-gray-500">{{ boxStats.customer.data?.customer.phone }}</dd>
                </div>
            </div>
        </BoxStatPallet>


        <!-- Box: Status -->
        <BoxStatPallet class="py-1 sm:py-3 px-3" :label="capitalize('dataPalletDelivery.state')" icon="fal fa-truck-couch">
            <!-- <div>
                Stats
            </div> -->

            <!-- Stats: count Pallets, Services, Physical Goods -->
            <div class="space-y-0.5">
                <div v-tooltip="trans('Count of pallets')" class="w-fit flex items-center gap-x-3">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-pallet' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                    </dt>
                    <dd class="text-gray-500 text-base font-medium tabular-nums">{{ locale.number(boxStats.stats.number_pallets) }} <span class="text-gray-400 font-normal">{{ boxStats.stats.number_pallets > 1 ? trans('Pallets') : trans('Pallet') }}</span></dd>
                </div>

                <div v-tooltip="trans('Count of stored item')" class="w-fit flex items-center gap-x-3">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-concierge-bell' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                    </dt>
                    <dd class="text-gray-500 text-base font-medium tabular-nums">{{ locale.number(boxStats.stats.number_stored_items) }} <span class="text-gray-400 font-normal">{{ boxStats.stats.number_stored_items > 1 ? trans('Stored items') : trans('Stored item') }}</span></dd>
                </div>

            </div>
        </BoxStatPallet>

        <!-- Box: Order summary -->
        <BoxStatPallet class="sm:col-span-2 border-t sm:border-t-0 border-gray-300">
            <section aria-labelledby="summary-heading" class="rounded-lg px-4 py-4 sm:px-6 lg:mt-0">
                <div v-if="boxStats?.invoice" class="mb-1 text-sm text-gray-500 ">
					<FontAwesomeIcon
						icon="fal fa-file-invoice"
						size="xs"
						fixed-width
						class="text-gray-400"
						:tooltip="'invoice'"
						aria-hidden="true" />
					<Link
						:href="
							route(
								boxStats?.invoice?.route?.name,
								boxStats?.invoice?.route?.parameters
							)
						"
						method="get"
						v-tooltip="'Recurring Bill'"
						class="primaryLink">
						{{ boxStats?.invoice?.reference }}
					</Link>
				</div>
                <OrderSummary :order_summary="boxStats.order_summary" :currency_code="props.currency?.data?.code" />

            </section>
        </BoxStatPallet>
    </div>
</template>