<script setup lang="ts">
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import { trans } from "laravel-vue-i18n"
import NeedToPay from "@/Components/Utils/NeedToPay.vue"
import { Address } from "@/types/PureComponent/Address"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faIdCardAlt, faEnvelope, faPhone, faGift, faBoxFull, faWeight } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { Link, router } from "@inertiajs/vue3"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { ref } from "vue"
import { routeType } from "@/types/route"
import { values } from "lodash"
library.add(faIdCardAlt, faEnvelope, faPhone, faGift, faBoxFull, faWeight)

const props = defineProps<{
	boxStats: {
		customer: {
			reference: string
			company_name: string
			contact_name: string
			email: string
			phone: string
			address: Address
		}
		products: {
			estimated_weight: number
			payment: {
				total_amount: number
				paid_amount?: number
				pay_amount?: number
				isPaidOff?: boolean
			}
			routes: {
				picker_list: routeType
				submit_picker: routeType
				packer_list: routeType
				submit_packer: routeType
			}
		}
		packer: object
		picker: object
        state: string
		warehouse: {
			picker: string
			packer: string
		}
	}
	routes: {
		pickers_list: routeType
		packers_list: routeType
		update: routeType
	}
}>()

</script>

<template>
	<div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-300 border-b border-gray-200">
		<BoxStatPallet class="py-2 px-3" icon="fal fa-user">
			<!-- Field: Reference Number -->
			<Link
				as="a"
				v-if="boxStats?.customer.reference"
				:href="'route(boxStats?.customer.route.name, boxStats?.customer.route.parameters)'"
				class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
				<dt v-tooltip="'Company name'" class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-id-card-alt"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-sm text-gray-500" v-tooltip="'Reference'">
					#{{ boxStats?.customer.reference }}
				</dd>
			</Link>

			<!-- Field: Contact name -->
			<div
				v-if="boxStats?.customer.contact_name"
				class="pl-1 flex items-center w-full flex-none gap-x-2"
				v-tooltip="trans('Contact name')">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-user"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-sm text-gray-500">{{ boxStats?.customer.contact_name }}</dd>
			</div>

			<!-- Field: Company name -->
			<div
				v-if="boxStats?.customer.company_name"
				class="pl-1 flex items-center w-full flex-none gap-x-2"
				v-tooltip="trans('Company name')">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-building"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-sm text-gray-500">{{ boxStats?.customer.company_name }}</dd>
			</div>

			<!-- Field: Email -->
			<div
				v-if="boxStats?.customer.email"
				class="pl-1 flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="'Email'" class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-envelope"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a
					:href="`mailto:${boxStats?.customer.email}`"
					v-tooltip="'Click to send email'"
					class="text-sm text-gray-500 hover:text-gray-700 truncate"
					>{{ boxStats?.customer.email }}</a
				>
			</div>

			<!-- Field: Phone -->
			<div
				v-if="boxStats?.customer.phone"
				class="pl-1 flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="'Phone'" class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-phone"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a
					:href="`tel:${boxStats?.customer.phone}`"
					v-tooltip="'Click to make a phone call'"
					class="text-sm text-gray-500 hover:text-gray-700"
					>{{ boxStats?.customer.phone }}</a
				>
			</div>

			<!-- Field: Address -->
			<div
				v-if="boxStats?.customer?.address"
				class="pl-1 flex items w-full flex-none gap-x-2"
				v-tooltip="trans('Shipping address')">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-shipping-fast"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd
					class="w-full text-gray-500 text-xs relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50"
					v-html="boxStats?.customer.address.formatted_address"></dd>
			</div>
		</BoxStatPallet>

		<!-- Box: Product stats -->
		<BoxStatPallet class="py-2.5 pl-1.5 pr-3" icon="fal fa-user">
			<div
				v-tooltip="trans('Estimated weight of all products')"
				class="flex items-center w-fit pr-3 flex-none gap-x-1.5">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-weight"
						fixed-width
						aria-hidden="true"
						class="text-gray-500" />
				</dt>
				<dd class="text-gray-500">
					{{ boxStats?.products.estimated_weight || 0 }} kilograms
				</dd>
			</div>
			
		</BoxStatPallet>
	</div>
</template>
