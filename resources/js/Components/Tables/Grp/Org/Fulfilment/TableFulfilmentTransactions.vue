<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 09:45:43 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRobot, faBadgePercent, faTag, faUserRobot, faPlus, faMinus, faUndoAlt } from '@far'
import { faTrashAlt } from '@fal'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { inject, ref } from "vue"
import InputNumber from 'primevue/inputnumber';
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { routeType } from '@/types/route'
import Tag from '@/Components/Tag.vue'
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"


library.add(faRobot, faPlus, faMinus, faUndoAlt, faTrashAlt)

const props = defineProps<{
	data: {}
	state: string
	tab?: string
	can_edit_transactions?: boolean
}>()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject("layout", layoutStructure)
// const emits = defineEmits<{
// 	(e: "renderTableKey"): void
// }>()

// const layout = inject('layout', layoutStructure)

// function serviceRoute(service: {}) {
//     // console.log(route().current())
//     switch (route().current()) {

//         case "grp.org.fulfilments.show.catalogue.services.index":
//             return route(
//                 'grp.org.fulfilments.show.catalogue.services.show',
//                 [route().params['organisation'], route().params['fulfilment'], service.slug])
//         default:
//             return null
//     }
// }

const getRouteUpdate = (idFulfilmentTransaction: number) => {
    const routeUpdate = <routeType>{}

	if (layout.app.name === "Aiku") {
		routeUpdate.name = "grp.models.fulfilment-transaction.update"
		routeUpdate.parameters = { fulfilmentTransaction: idFulfilmentTransaction }
	} else {
		routeUpdate.name = "retina.models.fulfilment-transaction.update"
		routeUpdate.parameters = { fulfilmentTransaction: idFulfilmentTransaction }
	}

    routeUpdate.method = 'patch'

    return routeUpdate
}

// Section: Quantity
const isLoading = ref<string | boolean>(false)
const onUpdateQuantity = (idFulfilmentTransaction: number, form: {}) => {

	const routeUpdate = getRouteUpdate(idFulfilmentTransaction)

	form.patch(route(routeUpdate.name, routeUpdate.parameters),{
		preserveScroll: true,
		onStart: () => (isLoading.value = "quantity" + idFulfilmentTransaction),
		onFinish: () => (isLoading.value = false),
	})
}
const onDeleteTransaction = (idFulfilmentTransaction: number) => {
	const routeDelete = <routeType>{}
	if (layout.app.name === "Aiku") {
		routeDelete.name = "grp.models.fulfilment-transaction.delete"
		routeDelete.parameters = { fulfilmentTransaction: idFulfilmentTransaction }
	} else {
		routeDelete.name = "retina.models.fulfilment-transaction.delete"
		routeDelete.parameters = { fulfilmentTransaction: idFulfilmentTransaction }
	}
	router.delete(route(routeDelete.name, routeDelete.parameters), {
		preserveScroll: true,
		onStart: () => (isLoading.value = "buttonReset" + idFulfilmentTransaction),
		onFinish: () => (isLoading.value = false),
	})
}

const userCanEdit = (item) => {
	if (item.is_auto_assign) return false
	else if (!item.is_auto_assign && props.state === 'in_process') return true
	else if (!item.is_auto_assign && props.can_edit_transactions) return true
}

</script>

<template>
	<Table :key="tab" :resource="data" :name="tab" class="mt-5">
		<!-- Column: Code -->
		<template #cell(code)="{ item }">
			{{ item.code || '-' }}
			<FontAwesomeIcon v-if="item.is_auto_assign" v-tooltip="trans('Auto assign')" :icon="faUserRobot" />
		</template>

		<!-- Column: Name -->
		<template #cell(name)="{ item }">
			{{ item["name"] }} ({{
			useLocaleStore().currencyFormat(item["currency_code"], item["price"])
			}}/{{ item["unit_abbreviation"] }})
			<Tag v-if="item['discount'] > 0" :theme="17">
				<template #label>
					<font-awesome-icon :icon="faTag" class="text-xs text-emerald-700" />
					{{ item["discount"] }}%
				</template>
			</Tag>
		</template>

		<template #cell(quantity)="{ item }">
			<div class="flex justify-end">
				<div v-if="item.edit_type !== 'net' && userCanEdit(item)">
					<NumberWithButtonSave v-model="item.quantity"   @onSave="(e)=>onUpdateQuantity(item.id, e)"/>
				</div>
				<div v-else>
					<Transition name="spin-to-right"><span :key="item.quantity">{{ locale.number(item.quantity) }} {{ item.asset_unit }}</span></Transition>
				</div>
			</div>
		</template>

		<template #cell(net_amount)="{ item, proxyItem }">
            <div class="relative">
                <template v-if="item.edit_type === 'net'">
                    <div class="w-72 float-right">
                        <NumberWithButtonSave
                            v-model="proxyItem.total"
                            :saveOnForm="true"
                            :routeSubmit="getRouteUpdate(item.id)"
                            keySubmit="net_amount"
                            :bindToTarget="{
                                mode: 'currency',
                                fluid: true,
                                currency: item.currency_code,
                                locale: 'en-US',
                                step: 0.25
                            }"
                        />
                    </div>
                </template>

                <Transition v-else name="spin-to-right">
                    <span :key="item.total">
                        {{ locale.currencyFormat(item.currency_code, item.total || 0) }}
                    </span>
                </Transition>
            </div>
        </template>

		<!-- Column: Action -->
		<template #cell(actions)="{ item }">
			<!-- <Button v-if="userCanEdit(item)" @click="() => onDeleteTransaction(item.id)"
				:loading="isLoading === 'buttonReset' + item.id" icon="fal fa-trash-alt" type="negative"
				v-tooltip="'Unselect this field'" /> -->
			<ModalConfirmationDelete
				:routeDelete="layout.app.name == 'Aiku' ? {name: 'grp.models.fulfilment-transaction.delete',  parameters: { fulfilmentTransaction: item.id }} : {name: 'retina.models.fulfilment-transaction.delete', parameters: { fulfilmentTransaction: item.id }}"
			>
				<template #default="{ isOpenModal, changeModel, isLoadingdelete }">
					<Button v-if="userCanEdit(item)"
						@click="changeModel"
						:loading="isLoadingdelete"
						icon="fal fa-trash-alt"
						type="negative"
						v-tooltip="trans('Unselect this field')"
					/>
				</template>

			</ModalConfirmationDelete>
		</template>
	</Table>
</template>

<style scoped>
::v-deep(.p-inputnumber) {
    border-bottom: 2px solid transparent;
    transition: border-color 0.3s;
}
::v-deep(.p-inputnumber:focus-within) {
    border-bottom: 2px solid #4b5563; /* gray-500 */
}

.cursor-not-allowed {
	opacity: 0.5;
	cursor: not-allowed;
}

.loading-circle {
	width: 1rem;
	height: 1rem;
	animation: spin 1s linear infinite;
}

.circle-path {
	stroke: #000;
	stroke-linecap: round;
	stroke-dasharray: 125.6;
	stroke-dashoffset: 0;
	animation: dash 1.5s ease-in-out infinite;
}

@keyframes spin {
	from {
		transform: rotate(0deg);
	}

	to {
		transform: rotate(360deg);
	}
}

@keyframes dash {
	0% {
		stroke-dashoffset: 125.6;
	}

	50% {
		stroke-dashoffset: 62.8;
		transform: rotate(45deg);
	}

	100% {
		stroke-dashoffset: 125.6;
		transform: rotate(360deg);
	}
}

.custom-input-number :deep(.p-inputnumber) {
	--p-inputnumber-button-width: 35px;
	height: 35px;
}
</style>

