<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 19:24:57 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Payment } from "@/types/payment"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faSeedling, faTimes } from "@fal"

library.add(faSeedling, faCheck, faTimes)
const locale = useLocaleStore();

defineProps<{
	data: object
	tab?: string
}>()

function paymentsRoute(payment: Payment) {
	return route(payment.route.name, payment.route.params )
	
}
</script>

<template>
	sfdfsdf
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(reference)="{ item: payment }">
			<div>
				<template v-if="payment.reference">
					<Link :href="paymentsRoute(payment)" class="primaryLink">
						{{ payment["reference"] }} - {{ `${payment['id']}` }}
					</Link>
				</template>
				<template v-else>
					<!-- <span class="text-gray-500 italic" style="opacity: 0.7">No reference</span> -->
                    <Link :href="paymentsRoute(payment)" class="primaryLink">
						{{ payment["reference"] }} {{ `${payment['id']}` }}
					</Link>
				</template>
			</div>
		</template>
		<template #cell(status)="{ item }">
			<Icon :data="item.state_icon" class="" />
		</template>
		<template #cell(amount)="{ item: item }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat( item.currency_code, item.amount)  }}</div>
        </template>
		<template #cell(refunded)="{ item: item }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat( item.currency_code, item.refunded)  }}</div>
        </template>
		<template #cell(date)="{ item }">
			<div class="text-gray-500 text-right">
				{{
					useFormatTime(item["date"], {
						localeCode: locale.language.code,
						formatTime: "aiku",
					})
				}}
				<!--   {{ useFormatTime(item.date) }} -->
			</div>
		</template>
	</Table>
</template>
