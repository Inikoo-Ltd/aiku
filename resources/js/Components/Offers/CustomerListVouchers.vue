<script setup lang="ts">
import { inject, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Icon from "@/Components/Icon.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope, faUsers } from "@fal"

library.add(faEnvelope, faUsers)

type RouteData = { name: string; parameters: Record<string, string | number>; method?: string }

type CustomerListVoucher = {
	id: number
	name: string
	code: string
	has_unique_codes: boolean
	state: object
	start_at: string | null
	end_at: string | null
	number_customers: number
	number_customers_used: number
	sales: number
	route: RouteData
	mailshot_route: RouteData | null
}

const props = defineProps<{
	vouchers: CustomerListVoucher[]
	canEdit: boolean
	currencyCode: string
}>()

const locale = inject("locale", aikuLocaleStructure)

const creatingMailshotFor = ref<number | null>(null)

const emailCustomers = (voucher: CustomerListVoucher) => {
	if (!voucher.mailshot_route) return

	router.post(
		route(voucher.mailshot_route.name, voucher.mailshot_route.parameters),
		{},
		{
			onStart: () => (creatingMailshotFor.value = voucher.id),
			onFinish: () => (creatingMailshotFor.value = null),
		}
	)
}
</script>

<template>
	<section class="px-4 py-6 border-t border-gray-200">
		<h2 class="text-lg font-semibold">{{ ctrans("Customer vouchers") }}</h2>
		<p class="text-sm text-gray-500">
			{{ ctrans("Vouchers given to a list of customers, such as Potential Comebacks, Dormant, or customers who ordered only once.") }}
		</p>

		<p v-if="!vouchers.length" class="mt-4 text-sm text-gray-500">
			{{ ctrans("No customer vouchers yet. Create one to pick the customers, the reward and the code.") }}
		</p>

		<div v-else class="mt-4 overflow-x-auto">
			<table class="min-w-full text-sm">
				<thead>
					<tr class="border-b border-gray-200 text-left text-gray-500">
						<th class="py-2 pr-4 font-medium">{{ ctrans("Voucher") }}</th>
						<th class="py-2 pr-4 font-medium">{{ ctrans("Code") }}</th>
						<th class="py-2 pr-4 font-medium">{{ ctrans("Valid until") }}</th>
						<th class="py-2 pr-4 font-medium text-right">{{ ctrans("Customers") }}</th>
						<th class="py-2 pr-4 font-medium text-right">{{ ctrans("Used") }}</th>
						<th class="py-2 pr-4 font-medium text-right">{{ ctrans("Sales") }}</th>
						<th class="py-2 font-medium"><span class="sr-only">{{ ctrans("Actions") }}</span></th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					<tr v-for="voucher in vouchers" :key="voucher.id">
						<td class="py-2 pr-4">
							<div class="flex items-center gap-x-2">
								<Icon :data="voucher.state" />
								<Link :href="route(voucher.route.name, voucher.route.parameters)" class="primaryLink">
									{{ voucher.name }}
								</Link>
							</div>
						</td>
						<td class="py-2 pr-4 font-mono">
							{{ voucher.has_unique_codes ? `${voucher.code}-…` : voucher.code }}
						</td>
						<td class="py-2 pr-4">
							{{ voucher.end_at ? useFormatTime(voucher.end_at, { localeCode: locale.language.code }) : "" }}
						</td>
						<td class="py-2 pr-4 text-right tabular-nums">{{ locale.number(voucher.number_customers) }}</td>
						<td class="py-2 pr-4 text-right tabular-nums">{{ locale.number(voucher.number_customers_used) }}</td>
						<td class="py-2 pr-4 text-right tabular-nums">{{ locale.currencyFormat(currencyCode, voucher.sales) }}</td>
						<td class="py-2 text-right">
							<Button
								v-if="canEdit && voucher.mailshot_route"
								type="tertiary"
								size="xs"
								icon="fal fa-envelope"
								:label="ctrans('Email customers')"
								:loading="creatingMailshotFor === voucher.id"
								@click="emailCustomers(voucher)" />
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</section>
</template>
