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
import { faFileInvoiceDollar, faHandHoldingUsd } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useFormatTime } from "@/Composables/useFormatTime"
library.add(faFileInvoiceDollar, faHandHoldingUsd)

const props = defineProps<{
	data: object
	tab?: string
}>()

const locale = useLocaleStore();

function spaceRoute(space) {
	switch (route().current()) {
		case "grp.org.fulfilments.show.crm.customers.show.spaces.index":
			return route("grp.org.fulfilments.show.crm.customers.show.spaces.show", [
				route().params["organisation"],
				route().params["fulfilment"],
				route().params["fulfilmentCustomer"],
				space.slug,
			])
		default:
			return null
	}
}

</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
	
		<template #cell(reference)="{ item: space }">
			<Link :href="spaceRoute(space)" class="primaryLink">
				{{ space["reference"] }}
			</Link>
		</template>

		<template #cell(start_at)="{ item: space }">
			{{ useFormatTime(space["start_at"]) }}
		</template>

		<template #cell(end_at)="{ item: space }">
			{{ useFormatTime(space["end_at"]) }}
		</template>
		<template #cell(rental)="{ item: space }">
			<span v-tooltip="space.rental_name">
				{{ space["rental"] }}
			</span>
		</template>
		<!-- <template #cell(start_at)="{ item: space }">
			{{ useFormatTime( space["start_at"], { localeCode: locale.language.code, formatTime: "aiku" }) }}
		</template>
		<template #cell(end_at)="{ item: space }">
			{{ useFormatTime( space["end_at"], { localeCode: locale.language.code, formatTime: "aiku" }) }}
		</template> -->
	</Table>
</template>
