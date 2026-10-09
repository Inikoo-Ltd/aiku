<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"

import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { Link, router } from "@inertiajs/vue3"
import Icon from "@/Components/Icon.vue"
import { faTimesSquare } from "@fas"
import { faTrashAlt, faPaperPlane, faInventory } from "@far"
import { faTruckLoading, faStickyNote, faPallet, faBox, faSortSizeUp } from "@fal"
import FieldEditableTable from "@/Components/FieldEditableTable.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ButtonEditTable from "@/Components/ButtonEditTable.vue"
import LocationFieldDelivery from "@/Components/LocationFieldDelivery.vue"
import StoredItemProperty from '@/Components/StoredItemsProperty.vue'
import { routeType } from "@/types/route"
import { Table as TSTable } from "@/types/Table"

import '@/Composables/Icon/PalletStateEnum'
import { ctrans } from "@/Composables/useTrans"
import { ref } from "vue"
import Tag from "@/Components/Tag.vue"

library.add(faTrashAlt, faPaperPlane, faInventory, faTruckLoading, faStickyNote, faTimesSquare, faPallet, faBox, faSortSizeUp)

const props = defineProps<{
    data: TSTable
    tab?: string
    state?: string
    tableKey: number
    locationRoute: routeType
    rentalRoute: routeType
    rentalList?: []
	storedItemsRoute: {
		store: routeType
		index: routeType
		delete: routeType
	}
}>()

const isLoading = ref<string | boolean>(false)
 
const emits = defineEmits<{
	(e: 'renderTableKey'): void
}>()


const typePallet = [
	{ label: ctrans('Pallet'), value: 'pallet' },
	{ label: ctrans('Box'), value: 'box' },
	{ label: ctrans('Oversize'), value: 'oversize' }
]


const onSaved = async (pallet: { form: {} }, fieldName: string) => {
	if (pallet[fieldName] != pallet.form.data()[fieldName]) {
		pallet.form.processing = true
		try {
			await axios.patch(route(pallet.updateRoute.name, pallet.deleteRoute.parameters), {
				[fieldName]: pallet.form.data()[fieldName],
			})
			onSavedSuccess(pallet, fieldName)

		} catch (error: any) {
			onSavedError(error, pallet, fieldName)
		}

		setTimeout(() => {
			pallet.form.wasSuccessful = false
		}, 3000)
	}
}

const onSavedRental = async (pallet: { form: {} }, fieldName: string) => {
    isLoading.value = fieldName + pallet.id
    // console.log('ee', pallet, '===', fieldName, '==', pallet.form.data())
	if (pallet[fieldName] != pallet.form.data()[fieldName]) {
		pallet.form.processing = true
		try {
			await axios.patch(route(pallet.updatePalletRentalRoute.name, pallet.updatePalletRentalRoute.parameters), {
				[fieldName]: pallet.form.data()[fieldName],
			})
			onSavedSuccess(pallet, fieldName)

		} catch (error: any) {
			onSavedError(error, pallet)
		}

		setTimeout(() => {
			pallet.form.wasSuccessful = false
		}, 3000)
	}
    isLoading.value = false
}



const onSavedSuccess = (pallet: { form: {} }, fieldName: string) => {
	pallet.form.processing = false
	pallet.form.wasSuccessful = true
	pallet.form.hasErrors = false
	pallet.form.clearErrors()
	pallet[fieldName] = pallet.form.data()[fieldName]
}

const onSavedError = (error: {}, pallet: { form: {} }) => {
	pallet.form.processing = false
	pallet.form.wasSuccessful = false
	pallet.form.hasErrors = true
	if (error.response && error.response.data && error.response.data.errors) {
		const errors = error.response.data.errors
		const setErrors = {}
		for (const er in errors) {
			setErrors[er] = errors[er][0]
		}
		pallet.form.setError(setErrors)
	} else {
		if (error.response.data.message)
			notify({
				title: "Failed to update",
				text: error.response.data.message,
				type: "error",
			})
	}
}


// Section: Stored Item
const selectedpallet = ref(null)
const storedItemToEdit = ref()
const isModalStoredItem = ref(false)
const onDelete = (sendRoute: routeType, pallet_stored_items: {}[], data : { id : ''}) => {
    const stored_items = [...pallet_stored_items].filter((item) => item.id != data.id)
    const finalData = {}
    for (const d of stored_items) finalData[d.id] = { quantity: d.quantity }
    // console.log('finalData', finalData)

    sendToServer(sendRoute, finalData)
}

const sendToServer = async (sendRoute: routeType, data : {}, replaceData?: boolean) => {
    // console.log('-=-=-=-=', props.saveRoute.name, props.saveRoute.parameters)
    router.post(
		route(sendRoute.name, sendRoute.parameters),
		replaceData ? data : { stored_item_ids: data },
		{
			preserveScroll: true,
			preserveState: true,
			onError: (e) => {
				notify({
					title: ctrans("Something went wrong"),
					text: ctrans("Failed to update the stored items"),
					type: "error"
				})
			},
			onSuccess: (e) => {
				isModalStoredItem.value = false
			},
			onBefore: () => {
			},
			onFinish: () => {
			}
		}
	)
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5" :key="tableKey">
		<!-- Column: type pallet icon -->
		<template #cell(type_icon)="{ item: pallet }">
			<Icon :data="pallet.type_icon" class="px-1" />
			<Icon :data="pallet['state_icon']" class="px-1" />
		</template>


		<!-- Column: state-->
		<template #cell(state)="{ item: palletDelivery }">
			<Icon :data="palletDelivery['state_icon']" class="px-1" />
		</template>


		<!-- Column: Type pallet -->
		<template #cell(type)="{ item: pallet }">
            <div class="w-32">
            <!-- <pre>{{ pallet.id }}</pre> -->
                <FieldEditableTable
                    :key="'typePallet' + pallet.id"
                    :data="pallet"
                    @onSave="onSaved"
                    :options="typePallet"
                    :fieldType="'select'"
                    label="label"
                    valueProp="value"
                    fieldName="type"
                    placeholder="Enter customer type"
                />
            </div>
		</template>


		<!-- Column: Pallet Reference -->
		<template #cell(customer_reference)="{ item }">
			<div v-if="state == 'in_process'" class="w-full">
				<FieldEditableTable
                    :data="item"
                    @onSave="onSaved"
                    fieldName="customer_reference"
					placeholder="Enter customer reference"
                />
			</div>

			<div v-else class="space-x-1 space-y-2">
				<span v-if="item.customer_reference">{{ item.customer_reference }}</span>
				<span v-if="item.notes" class="text-gray-400 text-xs">
					<FontAwesomeIcon v-tooltip="ctrans('Note')" icon='fal fa-sticky-note' class='text-gray-400' fixed-width aria-hidden='true' />
					{{ item.notes }}
				</span>
                <span v-else class="text-gray-400 text-xs">-</span>
			</div>
		</template>


		<!-- Column: Notes -->
		<template #cell(notes)="{ item }">
			<div v-if="state == 'in_process'" class="min-w-32">
				<FieldEditableTable
                    :data="item"
                    @onSave="onSaved"
                    fieldName="notes"
                    placeholder="Enter pallet notes"
                />
			</div>
			<div v-else>{{ item["notes"] }}</div>
		</template>


		<!-- Column: Stored Items -->
		<template #cell(stored_items)="{ item }">
			<!-- <StoredItemProperty
				
                :pallet="item"
				:saveRoute="item.storeStoredItemRoute"
				:storedItemsRoute="storedItemsRoute"
                :editable="props.state == 'in_process'"
                @renderTable="() => emits('renderTableKey')"
				prefixQuery="stored_items"
            /> -->

			<div v-if="props.state == 'in_process'" class="flex gap-x-1.5 gap-y-1.5 flex-wrap">
                <template v-if="item?.stored_items?.length">
                    <div v-for="stored_item of item.stored_items" class="cursor-pointer">
                        <Tag @onClose="(event) => { event.stopPropagation(), onDelete(item.storeStoredItemRoute, item.stored_items, stored_item) }"
                            :label="`${stored_item.reference}`"
                            :closeButton="true"
							:stringToColor="true"
                            @click="() => (selectedpallet = item, storedItemToEdit = stored_item, isModalStoredItem = true)"
                        >
                            <template #label>
                                <div class="whitespace-nowrap text-xs">
                                    {{ stored_item.reference }} (<span class="font-light">{{ stored_item.quantity }}</span>)
                                </div>
                            </template>
                        </Tag>
                    </div>
                </template>
                
                <Button icon="fal fa-plus" @click="() => (selectedpallet = item, isModalStoredItem = true)" :type="'dashed'" :size="'xs'"/>
            </div>

			<!-- State: Submitted and Confirmed -->
			<div v-else-if="props.state == 'submitted' || props.state == 'confirmed'" class="flex gap-x-1.5 gap-y-1.5 flex-wrap">
                <template v-if="item?.stored_items?.length">
					<Tag
						v-for="item of item.stored_items"
						:key="item.id"
						:theme="item.id"
						:label="`${item.reference}`"
						v-tooltip="item.name"
						stringToColor
					>
						<template #label>
							<div class="whitespace-nowrap text-xs">
								{{ item.reference }} <span v-if="item.delivered_quantity" class="font-light">({{ item.quantity }})</span>
							</div>
						</template>
					</Tag>
                </template>
                
				<div v-else class="pl-2.5 text-gray-400">
					-
				</div>
            </div>

			<div v-else class="flex gap-x-1.5 gap-y-1.5 flex-wrap">
                <template v-if="item?.stored_items?.length">
					<Tag
						v-for="item of item.stored_items"
						:key="item.id"
						:theme="item.id"
						:label="`${item.reference}`"
						v-tooltip="item.name"
						stringToColor
					>
						<template #label>
							<div class="whitespace-nowrap text-xs">
								{{ item.reference }} <span v-if="item.delivered_quantity" class="font-light">({{ item.delivered_quantity }})</span>
							</div>
						</template>
					</Tag>
                </template>
                
				<div v-else class="pl-2.5 text-gray-400">
					-
				</div>
            </div>
			<!-- <pre>{{ item.stored_items }}</pre> -->
		</template>


		<!-- Column: Set Location -->
		<template #cell(location)="{ item: pallet }">
			<div v-if="state == 'booking_in'" class="flex gap-x-1 gap-y-2 items-center">
				<LocationFieldDelivery
					noButton
					noFetchOnMounted
                    :key="pallet.state"
                    :pallet="pallet"
					@renderTableKey="() => emits('renderTableKey')"
                    :locationRoute="locationRoute"
					:initOptions="pallet.location_id ? [{ id: pallet.location_id, code: pallet.location_code }] : []"
                />
			</div>
            
            <template v-else>
                <Tag v-if="pallet.location_code" :label="pallet.location_code" />
                <div v-else class="pl-4 text-gray-400">-</div>
            </template>
		</template>


		<!-- Column: Rental -->
		<template #cell(rental)="{ item: pallet }">
			<div v-if="props.state == 'booked_in'">{{ pallet.rental_name }}</div>
			<div v-else class="w-64">
                <FieldEditableTable
                    :key="'rental_id' + pallet.id"
                    :options="props.rentalList"
                    :data="pallet"
                    :isLoading="isLoading === 'rental_id' + pallet.id"
                    @onSave="onSavedRental"
                    fieldType="select"
                    fieldName="rental_id"
                    placeholder="Select rental"
                    label="name"
                    value-prop="id"
                />
            </div>
		</template>


		<!-- Column: Actions -->
		<template #cell(actions)="{ item: pallet }">
			<!-- State: Delete Pallet (in_process) -->
			<div v-if="props.state == 'in_process'">
				<Link
                    :href="route(pallet.deleteRoute.name, pallet.deleteRoute.parameters)"
                    method="delete"
                    preserve-scroll
                    as="div"
                    @start="() => isLoading = 'delete' + pallet.id"
                    v-tooltip="'Delete this pallet'"
                >
                    <Button icon="far fa-trash-alt" :loading="isLoading === 'delete' + pallet.id" type="negative" />
				</Link>
			</div>


			<!-- State: not received -->
			<div v-else-if="pallet.state == 'not_received'">
				<ButtonEditTable class="mx-2" type="secondary" label="Undo" :capitalize="false" :size="'xs'"
					:key="pallet.index" routeName="undoNotReceivedRoute" :data="pallet"
					@onSuccess="() => emits('renderTableKey')" />
			</div>


			<!-- State: Received -->
			<div v-else-if="(props.state == 'received' || props.state == 'booking_in' || props.state == 'not_received') && !pallet.location_id" class="flex">
				<ButtonEditTable class="mx-2" :type="pallet.state == 'not_received' ? 'secondary' : 'negative'"
					:icon="['fal', 'times']" tooltip="Set as not received" :size="'xs'" :key="pallet.index"
					routeName="notReceivedRoute" :data="pallet" @onSuccess="() => emits('renderTableKey')" />
			</div>
		</template>
	</Table>
	
	<StoredItemProperty
		:isModalOpened="isModalStoredItem"
		:pallet="selectedpallet"
		:saveRoute="selectedpallet?.storeStoredItemRoute"
		:storedItemsRoute="storedItemsRoute"
		:editable="props.state == 'in_process'"
		@xxxrenderTable="() => emits('renderTableKey')"
		@onCloseModal="isModalStoredItem = false"
		@onSuccessSubmit="isModalStoredItem = false"
		prefixQuery="stored_items"
		:sendToServerOptions="{ preserveScroll: true, preserveState: true }"
		:storedItemToEdit="storedItemToEdit"
	>
		<div />
	</StoredItemProperty>
</template>
