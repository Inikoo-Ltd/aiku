<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 27 Apr 2024 18:34:20 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { RecurringBill } from "@/types/recurring_bill"
import { Link } from "@inertiajs/vue3"
import { Pallet } from "@/types/Pallet"
import { faCheckCircle, faFileInvoiceDollar, faHandHoldingUsd, faTimesCircle } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useFormatTime } from "@/Composables/useFormatTime"
import Icon from "@/Components/Icon.vue"
library.add(faFileInvoiceDollar, faHandHoldingUsd, faCheckCircle, faTimesCircle)

const props = defineProps<{
	data: object
	tab?: string
}>()

function recurringBillRoute(bill) {
	switch (route().current()) {
		case "grp.org.fulfilments.show.crm.customers.show.recurring_bills.index":
			return route("grp.org.fulfilments.show.crm.customers.show.recurring_bills.show", [
				route().params["organisation"],
				route().params["fulfilment"],
				route().params["fulfilmentCustomer"],
				bill.slug,
			])
    case "grp.org.fulfilments.show.operations.recurring_bills.current.index":
      return route("grp.org.fulfilments.show.operations.recurring_bills.current.show", [
        route().params["organisation"],
        route().params["fulfilment"],
        bill.slug,
      ])

		case "grp.org.fulfilments.show.operations.recurring_bills.former.index":
			return route("grp.org.fulfilments.show.operations.recurring_bills.former.show", [
				route().params["organisation"],
				route().params["fulfilment"],
				bill.slug,
			])

		default:
			return []
	}
}

function fulfilmentCustomerRoute(bill) {
  return route("grp.org.fulfilments.show.crm.customers.show", [
    route().params["organisation"],
    route().params["fulfilment"],
    bill.fulfilment_customer_slug,
  ])
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(status_icon)="{ item: bill }">
			<Icon :data="bill.status_icon" />
		</template>

		<template #cell(reference)="{ item: bill }">
			<Link :href="recurringBillRoute(bill)" class="primaryLink">
				{{ bill["reference"] }}
			</Link>
		</template>

		<!-- Column: Net -->
		<template #cell(net_amount)="{ item: bill }">
			<div class="text-gray-500 text-right">
				{{ useLocaleStore().currencyFormat(bill.currency_code, bill.net_amount) }}
			</div>
		</template>

		<template #cell(customer_name)="{ item: bill }">
			<Link :href="fulfilmentCustomerRoute(bill)" class="secondaryLink">
				{{ bill["customer_name"] }}
			</Link>
		</template>

		<!-- Column: Start date -->
		<template #cell(start_date)="{ item }">
			<div class="text-right">
				{{ useFormatTime(item.start_date) }}
			</div>
		</template>

		<!-- Column: End date -->
		<template #cell(end_date)="{ item }">
			<div class="text-right">
				{{ useFormatTime(item.end_date) }}
			</div>
		</template>
	</Table>
</template>
