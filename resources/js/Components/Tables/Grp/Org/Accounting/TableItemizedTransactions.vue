<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { inject, ref } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { Link, router } from "@inertiajs/vue3"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { routeType } from "@/types/route"
import Tag from "@/Components/Tag.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale"

defineProps<{
	data: object
	tab: string
	status?: string
}>()

const locale = inject("locale", aikuLocaleStructure)
const layout = inject("layout", layoutStructure)

const getRoute = (item: {}) => {
	const routeUpdate = <routeType>{}

	if (item?.fulfilment_transaction_id) {
		if (layout.app.name === "Aiku") {
			routeUpdate.name = "grp.models.fulfilment-transaction.update"
			routeUpdate.parameters = { fulfilmentTransaction: item?.fulfilment_transaction_id }
		} else {
			routeUpdate.name = "retina.models.fulfilment-transaction.update"
			routeUpdate.parameters = { fulfilmentTransaction: item?.fulfilment_transaction_id }
		}
	} else {
		routeUpdate.name = "grp.models.recurring_bill_transaction.update"
		routeUpdate.parameters = { recurringBillTransaction: item?.id }
	}

	routeUpdate.method = "patch"

	return routeUpdate
}

const onUpdateQuantity = (id: Number, fulfilment_transaction_id: number, value: number) => {
	/* console.log(idFulfilmentTransaction, 'loasding', value); */

	const routeUpdate = <routeType>{}
	if (fulfilment_transaction_id) {
		if (layout.app.name === "Aiku") {
			routeUpdate.name = "grp.models.fulfilment-transaction.update"
			routeUpdate.parameters = { fulfilmentTransaction: fulfilment_transaction_id }
		} else {
			routeUpdate.name = "retina.models.fulfilment-transaction.update"
			routeUpdate.parameters = { fulfilmentTransaction: fulfilment_transaction_id }
		}
	} else {
		routeUpdate.name = "grp.models.recurring_bill_transaction.update"
		routeUpdate.parameters = { recurringBillTransaction: id }
	}
	value.patch(route(routeUpdate.name, routeUpdate.parameters), {
		preserveScroll: true,
	})
}

const isLoading = ref<string | boolean>(false)
const onDeleteTransaction = (id: Number, fulfilment_transaction_id: number) => {
	const routeDelete = <routeType>{}
	if (fulfilment_transaction_id) {
		if (layout.app.name === "Aiku") {
			routeDelete.name = "grp.models.fulfilment-transaction.delete"
			routeDelete.parameters = { fulfilmentTransaction: fulfilment_transaction_id }
		} else {
			routeDelete.name = "retina.models.fulfilment-transaction.delete"
			routeDelete.parameters = { fulfilmentTransaction: fulfilment_transaction_id }
		}
	} else {
		routeDelete.name = "grp.models.recurring_bill_transaction.delete"
		routeDelete.parameters = { recurringBillTransaction: id }
	}

	router.delete(route(routeDelete.name, routeDelete.parameters), {
		preserveScroll: true,
		onStart: () => (isLoading.value = "buttonReset" + id),
		onFinish: () => (isLoading.value = false),
	})
}
</script>

<template>
    <pre>{{ data }}</pre>
	<div class="h-min">
		<Table :resource="data" :name="tab" class="mt-5" :is-check-box="false">
			<template #cell(description)="{ item }">
				<div
					v-if="
						item.description?.model ||
						item.description?.title ||
						item.description?.after_title
					">
					<span v-if="item.description?.model">{{ item.description.model }}:</span>
					<Link
						v-if="item.description?.title && item.description.route?.name"
						:href="
							route(item.description.route?.name, item.description.route?.parameters)
						"
						class="primaryLink">
						{{ item.description.title }}
					</Link>
					<span v-else>&nbsp;{{ item.description.title }}</span>

					<div v-if="item.description.after_title" class="text-gray-400 italic text-xs">
						({{ item.description.after_title }})
					</div>
				</div>

				<div v-else></div>
			</template>

            <template #cell(net_amount)="{ item }">
                <div class="text-gray-500">{{ useLocaleStore().currencyFormat( item.currency_code, item.net_amount)  }}</div>
            </template>
		</Table>
	</div>
</template>
