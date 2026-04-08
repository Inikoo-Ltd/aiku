<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 19:24:57 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Payment } from "@/types/payment"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faExclamation, faFileCheck, faFileTimes, faHandshakeAltSlash, faHourglassHalf, faSeedling, faSpinner, faTimes } from "@fal"
import { inject, computed } from "vue"
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faSeedling, faCheck, faTimes, faSpinner, faHourglassHalf, faFileCheck, faFileTimes, faExclamation, faHandshakeAltSlash)
const locale = useLocaleStore();
const layout = inject('layout')
defineProps<{
	data: object
	tab?: string
}>()

function paymentsRoute(payment: Payment) {
	return route(payment.route.name, payment.route.params)

}

const formatReference = (payment) => {
	if (payment?.reference) {
		return `${payment.reference} - ${payment.id}`
	}
	return `${payment.id}`
}

const cancelConfirmationText = (item) => {
	return item.payment_account_type === 'account' ? trans('This payment will be cancelled. This action would also affect the customer balance.') : trans('This payment will be cancelled.');
}

</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(reference)="{ item: payment }">
			<div>
				<component :is="!layout.retina ? Link : 'span'" v-if="!layout.retina" :href="paymentsRoute(payment)"
					class="primaryLink">
					{{ formatReference(payment) }}
				</component>

				<template v-else>
					{{ formatReference(payment) }}
				</template>
			</div>
		</template>

		<template #cell(status)="{ item }">
			<Icon v-if="item.is_cancelled === false" :data="item.status_icon" class="" />
			<FontAwesomeIcon v-else :icon="faFileTimes" class="text-orange-500 text-lg" v-tooltip="trans('This payment is being cancelled')"/>
		</template>

		<template #cell(amount)="{ item: item }">
			<div class="text-gray-500">{{ useLocaleStore().currencyFormat(item.currency_code, item.amount) }}</div>
		</template>

		<template #cell(refunded)="{ item: item }">
			<div class="text-gray-500">{{ useLocaleStore().currencyFormat(item.currency_code, item.refunded) }}</div>
		</template>

		<template #cell(actions)="{item: item}">
			<ModalConfirmation
				v-if="item.is_cancelled === false" 
				:routeYes="{
					name: 'grp.models.org.payment.cancel',
					parameters: {
						organisation: item.organisation_id,
						payment: item.id
					},
					method: 'patch'
				}"
				:title="trans('Are you sure you want to cancel this payment?')"
				:description="cancelConfirmationText(item)"
				:noLabel="trans('Return')"
				:iconClass="'text-red-500'"
				:iconContainerClass="'bg-red-100 border-1 border-red-500'"
			>
				<template #default="{ changeModel }">
					<Button 
						v-tooltip="trans('Cancel Payment')"
						class="text-sm" :type="'negative'"
						@click="changeModel"
					>
						<FontAwesomeIcon :icon="faFileTimes" class="text-red-500"/>
					</Button>
				</template>

				<template #btn-yes="{ isLoadingdelete, clickYes}">
					<Button
						:style="'delete'"
						:loading="isLoadingdelete"
						@click="() => clickYes()"
						:label="trans('Cancel Payment')"
					/>
				</template>
			</ModalConfirmation>
		</template>
	</Table>
</template>
