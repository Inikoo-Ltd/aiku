<script setup lang="ts">
import { computed } from "vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink} from '@far'
import { faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faMoneyBillWave } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Tag from "@/Components/Tag.vue"
import { useLocaleStore } from "@/Stores/locale"
library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faMoneyBillWave)


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
	// Optional: if customer state or dropshipping info is provided
	state?: string
	is_dropshipping?: boolean
}

interface Showcase {
	parent_type: string
	amount: string
	state: string
	customer: { data: CustomerData }
	parent_data: any
	currency: { data: any }
	paymentAccount: { data: any }
}

const props = defineProps<{
	data: Showcase
	tab: string
}>()

// Normalize the showcase data so that the nested properties (like customer.data)
// are flattened and available directly in the template.
const normalizedShowcase = computed(() => {
	// Merge the root-level state into the customer if it's not already defined
	const customer = { ...props.data.customer.data }
	if (!customer.state) {
		customer.state = props.data.state
	}
	return {
		...props.data,
		customer,
		currency: props.data.currency.data,
		paymentAccount: props.data.paymentAccount.data,
	}
})
</script>

<template>
	<div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-2 gap-x-8 gap-y-3">
		<!-- Section: Profile box -->
		<div>
			<div class="rounded-lg shadow-sm ring-1 ring-gray-900/5">
				<dl class="flex flex-wrap">
					<!-- Profile: Header -->
					<div class="flex w-full py-6">
						<!-- Customer state tag -->
						<div class="flex-none self-end px-6">
							<dt class="sr-only">state</dt>
							<dd class="capitalize">
								<Tag
									:label="normalizedShowcase.customer?.state"
									:theme="
										normalizedShowcase.customer?.state === 'active'
											? 3
											: normalizedShowcase.customer?.state === 'lost'
											? 7
											: 99
									" />
							</dd>
						</div>
					</div>

					<!-- Section: Customer Details -->
					<div class="flex flex-col gap-y-3 border-t border-gray-900/5 w-full py-6">
						<!-- Field: Contact name -->
						<div
							v-if="normalizedShowcase.customer?.contact_name"
							class="flex items-center w-full flex-none gap-x-4 px-6">
							<dt v-tooltip="trans('Contact name')" class="flex-none">
								<span class="sr-only">Contact name</span>
								<FontAwesomeIcon
									icon="fal fa-male"
									class="text-gray-400"
									fixed-width
									aria-hidden="true" />
							</dt>
							<dd class="text-gray-500">
								{{ normalizedShowcase.customer.contact_name }}
							</dd>
						</div>
                        <div
							v-if="normalizedShowcase.amount"
							class="flex items-center w-full flex-none gap-x-4 px-6">
							<dt v-tooltip="trans('Amount')" class="flex-none">
								<span class="sr-only">Amount</span>
								<FontAwesomeIcon
									icon="fal fa-money-bill-wave"
									class="text-gray-400"
									fixed-width
									aria-hidden="true" />
							</dt>
							<dd class="text-gray-500">
                                {{ useLocaleStore().currencyFormat( normalizedShowcase.currency.code, normalizedShowcase.amount)  }}
							</dd>
						</div>
						<!-- Field: Company name -->
						<div
							v-if="normalizedShowcase.customer?.company_name"
							class="flex items-center w-full flex-none gap-x-4 px-6">
							<dt v-tooltip="trans('Company name')" class="flex-none">
								<span class="sr-only">Company name</span>
								<FontAwesomeIcon
									icon="fal fa-building"
									class="text-gray-400"
									fixed-width
									aria-hidden="true" />
							</dt>
							<dd class="text-gray-500">
								{{ normalizedShowcase.customer.company_name }}
							</dd>
						</div>

						<!-- Field: Created at -->
						<div
							v-if="normalizedShowcase.customer?.created_at"
							class="flex items-center w-full flex-none gap-x-4 px-6">
							<dt v-tooltip="trans('Created at')" class="flex-none">
								<span class="sr-only">Created at</span>
								<FontAwesomeIcon
									icon="fal fa-calendar-alt"
									class="text-gray-400"
									fixed-width
									aria-hidden="true" />
							</dt>
							<dd class="text-gray-500">
								<time :datetime="normalizedShowcase.customer.created_at">
									{{ useFormatTime(normalizedShowcase.customer.created_at) }}
								</time>
							</dd>
						</div>

						<!-- Field: Email -->
						<div
							v-if="normalizedShowcase.customer?.email"
							class="flex items-center w-full flex-none gap-x-4 px-6">
							<dt v-tooltip="trans('Email')" class="flex-none">
								<span class="sr-only">Email</span>
								<FontAwesomeIcon
									icon="fal fa-envelope"
									class="text-gray-400"
									fixed-width
									aria-hidden="true" />
							</dt>
							<dd class="text-gray-500">
								<a :href="`mailto:${normalizedShowcase.customer.email}`">
									{{ normalizedShowcase.customer.email }}
								</a>
							</dd>
						</div>

						<!-- Field: Phone -->
						<div
							v-if="normalizedShowcase.customer?.phone"
							class="flex items-center w-full flex-none gap-x-4 px-6">
							<dt v-tooltip="trans('Phone')" class="flex-none">
								<span class="sr-only">Phone</span>
								<FontAwesomeIcon
									icon="fal fa-phone"
									class="text-gray-400"
									fixed-width
									aria-hidden="true" />
							</dt>
							<dd class="text-gray-500">
								<a :href="`tel:${normalizedShowcase.customer.phone}`">
									{{ normalizedShowcase.customer.phone }}
								</a>
							</dd>
						</div>

						
					</div>
				</dl>
			</div>
		</div>
	</div>
</template>
