<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, defineAsyncComponent, ref } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import TablePurchaseOrderTransactions from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrderTransactions.vue"
import { capitalize } from "@/Composables/capitalize"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { PageHeading as PageHeadingTS } from "@/types/PageHeading"
import { BoxNote as BoxNoteTS } from "@/types/Components/BoxNotes"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import AlertMessage from "@/Components/Utils/AlertMessage.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import BoxNote from "@/Components/Pallet/BoxNote.vue"
import Popover from "@/Components/Popover.vue"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import TableProductList from "@/Components/Tables/Grp/Helpers/TableProductList.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import ModalProductList from "@/Components/Utils/ModalProductList.vue"
import { Timeline as TSTimeline } from "@/types/Timeline"
import { Currency } from "@/types/LayoutRules"
import Modal from "@/Components/Utils/Modal.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import axios from "axios"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faExclamationTriangle, faExclamation, faPencil } from "@fas"
import {
	faStickyNote,
	faPaperclip,
	faDollarSign,
	faIdCardAlt,
	faShippingFast,
	faIdCard,
	faEnvelope,
	faPhone,
	faWeight,
	faTruck,
	faFilePdf,
} from "@fal"
import { Action } from "@/types/Action"
import { get } from 'lodash-es'
import { faMinus, faPlus } from "@far"
import { PalletDelivery } from "@/types/Pallet"
library.add(
	faStickyNote,
	faPaperclip,
	fadExclamationTriangle,
	faExclamationTriangle,
	faDollarSign,
	faIdCardAlt,
	faShippingFast,
	faIdCard,
	faEnvelope,
	faPhone,
	faWeight,
	faStickyNote,
	faExclamation,
	faTruck,
	faFilePdf,
	faPaperclip,
	faPencil,
	faPlus,
	faMinus
)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTS
	tabs: {
		current: string
		navigation: {}
	}
    currency: {}
	data?: {
		data: PalletDelivery
	}
	showcase: {}
	transactions: {}
	history: {}
	alert?: {
		status: string
		title?: string
		description?: string
	}
	routes: {
		updatePurchaseOrderRoute: routeType
		products_list: routeType
	}
	attachments?: {}
	attachmentRoutes?: {}
	timelines: {
		[key: string]: TSTimeline
	}
	box_stats: {
		orderer: {
			data: {
				code: string
				company_name: string
				contact_name: string
				email: string
				name: string
			}
			type: string
		}
		mid_block: {
			gross_weight: string
			net_weight: string
			notes: string
			delivery_state: string
		}
		order_summary: {}
	}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component = computed(() => {
	const components: Component = {
		history: TableHistories,
		transactions: TablePurchaseOrderTransactions,
		attachments: TableAttachments,
		products: TableProductList
	}

	return components[currentTab.value]
})
const isModalOpen = ref(false)
const noteModalValue = ref(props.box_stats.mid_block.notes || "")
const currentAction = ref(null);
const isLoadingButton = ref<string | boolean>(false)
const isModalUploadOpen = ref(false)
const isSubmitNoteLoading = ref(false)

//submit notes
const onSubmitNote = async () => {
	isSubmitNoteLoading.value = true

	try {
		const response = await axios.patch(
			route(
				props.routes.updatePurchaseOrderRoute.name,
				props.routes.updatePurchaseOrderRoute.parameters
			),
			{
				notes: noteModalValue.value,
			},
			{
				headers: { "Content-Type": "application/json" },
			}
		)
		props.box_stats.mid_block.notes = noteModalValue.value
	} catch (error) {
		console.log(error, "faf")

		notify({
			title: "Failed",
			text: "Failed to update the note, try again.",
			type: "error",
		})
	}

	isSubmitNoteLoading.value = false
	isModalOpen.value = false
}

const closeModal = () => {
	isModalOpen.value = false
	noteModalValue.value = props.box_stats.mid_block.notes || ""
}

const openModal = (action :any) => {
	currentAction.value = action;
    isModalUploadOpen.value = true;
};

const fallbackBgColor = "#f9fafb" // Background
const fallbackColor = "#374151"
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" v-if="currentTab != 'products'">
		<template #button-add-products="{ action }" >
			<div class="relative">
				<Popover>
					<template #button="{ open }">
						<Button
							:style="action.style"
							:label="action.label"
							:icon="action.icon"
							@click="() => openModal(action)"
							:key="`ActionButton${action.label}${action.style}`"
							:tooltip="action.tooltip" />
					</template>

					<!-- <template #content="{ close: closed }">
						<div class="w-[350px]">
							<div class="text-xs px-1 my-2">{{ trans("Products") }}:</div>
							<div class="">
								<PureMultiselectInfiniteScroll
									v-model="formProducts.historic_id"
									:fetchRoute="routes.products_list"
									:placeholder="trans('Select Products')"
									>
									<template #singlelabel="{ value }">
										<div class="w-full text-left pl-4">
											{{ value.name }}
											
										</div>
									</template>

									<template #option="{ option, isSelected, isPointed }">
										<div
											class="w-full flex items-center justify-between gap-x-3">
											<div
												:class="
													isSelected(option)
														? option.stock
															? ''
															: 'text-indigo-200'
														: option.stock
														? ''
														: 'text-gray-400'
												">
												{{ option.name }}
											
											</div>

											<FontAwesomeIcon
												v-if="option.stock === 0"
												v-tooltip="trans('No stock')"
												icon="fas fa-exclamation-triangle"
												class="text-red-500"
												fixed-width
												aria-hidden="true" />
											<FontAwesomeIcon
												v-else-if="option.stock < 10"
												icon="fas fa-exclamation"
												class="text-yellow-500"
												fixed-width
												aria-hidden="true" />
										</div>
									</template>
								</PureMultiselectInfiniteScroll>

								<p
									v-if="get(formProducts, ['errors', 'historic_id'])"
									class="mt-2 text-sm text-red-500">
									{{ formProducts.errors.historic_id }}
								</p>
							</div>

							<div class="mt-4">
								<div class="text-xs px-1 my-2">{{ trans("Quantity") }}:</div>
								<PureInput
									v-model="formProducts.quantity_ordered"
									:placeholder="trans('Quantity')"
									@keydown.enter="() => onSubmitAddProducts(action, closed)" />
								<p
									v-if="get(formProducts, ['errors', 'quantity_ordered'])"
									class="mt-2 text-sm text-red-600">
									{{ formProducts.errors.quantity_ordered }}
								</p>
							</div>

							<div class="flex justify-end mt-4">
								<Button
									@click="() => onSubmitAddProducts(action, closed)"
									:style="'save'"
									:loading="isLoadingButton == 'addProducts'"
									:disabled="
										!formProducts.historic_id ||
										formProducts.quantity_ordered < 1
									"
									label="Save"
									full />
							</div> -->

					<!-- Loading: fetching service list -->
					<!-- <div v-if="isLoadingData === 'addProducts'" class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-5xl' fixed-width aria-hidden='true' />
                            </div> -->
					<!-- </div>
					</template> -->
				</Popover>
			</div>
		</template>
	</PageHeading>

	<!-- Section: Pallet Warning -->
	<div v-if="alert?.status" class="p-2 pb-0">
		<AlertMessage :alert />
	</div>

	<!-- Section: Timeline -->
	<div
		v-if="data?.data?.state != 'in_process' && currentTab != 'products'"
		class="mt-4 sm:mt-0 border-b border-gray-200 pb-2">
		<Timeline
			v-if="timelines"
			:options="timelines"
			:state="props.data?.data?.state"
			:slidesPerView="6" />
	</div>

	<div v-if="currentTab != 'products'" class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-300 border-b border-gray-200">
		<BoxStatPallet class="py-2 px-3" icon="fal fa-user">
			<!-- Field: Reference Number -->
			<div
				v-if="box_stats?.orderer.data.code"
				class="pl-1 flex items-center w-fit flex-none gap-x-2">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-user"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-sm text-gray-500">{{ box_stats?.orderer.data.code }}</dd>
			</div>

			<!-- Field: Contact name -->
			<div
				v-if="box_stats?.orderer.data.name"
				v-tooltip="trans('Contact name')"
				class="pl-1 flex items-center w-full flex-none gap-x-2">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-id-card-alt"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-sm text-gray-500">{{ box_stats?.orderer.data.name }}</dd>
			</div>

			<!-- Field: Company name -->
			<div
				v-if="box_stats?.orderer.data.company_name"
				v-tooltip="trans('Company name')"
				class="pl-1 flex items-center w-full flex-none gap-x-2">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-building"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-sm text-gray-500">{{ box_stats?.orderer.data.company_name }}</dd>
			</div>

			<!-- Field: Email -->
			<div
				v-if="box_stats?.orderer.data.email"
				class="pl-1 flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Email')" class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-envelope"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a
					:href="`mailto:${box_stats?.orderer.data.email}`"
					v-tooltip="'Click to send email'"
					class="text-sm text-gray-500 hover:text-gray-700 truncate"
					>{{ box_stats?.orderer.data.email }}</a
				>
			</div>

			<!-- Field: Phone -->
			<div
				v-if="box_stats?.orderer.data.contact_name"
				class="pl-1 flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Phone')" class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-phone"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a
					:href="`tel:${box_stats?.orderer.data.contact_name}`"
					v-tooltip="'Click to make a phone call'"
					class="text-sm text-gray-500 hover:text-gray-700"
					>{{ box_stats?.orderer.data.contact_name }}</a
				>
			</div>
		</BoxStatPallet>

		<!-- Box: Product stats -->
		<BoxStatPallet class="py-4 pl-1.5 pr-3" icon="fal fa-user">
			<div class="mt-1 flex items-center w-full flex-none gap-x-1.5">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-weight"
						fixed-width
						aria-hidden="true"
						class="text-gray-500" />
				</dt>
				<dd class="text-gray-500 sep" v-tooltip="trans('Estimated weight of all products')">
					{{ box_stats?.mid_block.net_weight || 0 }} kilograms
				</dd>
			</div>
			<div class="relative flex items-start w-full gap-x-1">
				<dt class="flex-none pt-0.5">
					<FontAwesomeIcon
						icon="fal fa-sticky-note"
						fixed-width
						aria-hidden="true"
						class="text-gray-500" />
				</dt>

				<!-- Section: Note -->
				<div
					class="relative h-full flex flex-col items-center w-full p-4 bg-white rounded-lg border border-gray-200"
					:style="{
						backgroundColor: fallbackBgColor,
						color: fallbackColor,
					}">
					<!-- Edit Icon in Corner -->

					<div
						v-if="box_stats.mid_block.notes"
						@click="isModalOpen = true"
						v-tooltip="trans('Edit note')"
						class="absolute top-2 right-2 group cursor-pointer w-fit h-5 flex items-center">
						<FontAwesomeIcon
							icon="fas fa-pencil"
							size="xs"
							class="group-hover:text-gray-600 text-gray-500"
							fixed-width
							aria-hidden="true" />
					</div>

					<div
						v-else
						@click="isModalOpen = true"
						class="absolute top-2 right-2 group cursor-pointer w-fit h-5 flex items-center">
						<FontAwesomeIcon
							v-tooltip="trans('Add note')"
							icon="far fa-plus"
							class=""
							fixed-width
							aria-hidden="true"
							:style="{
								color: fallbackColor,
							}" />
					</div>

					<!-- Note Text -->
					<p
						class="text-xs md:text-sm break-words w-full"
						:style="{
							color: fallbackColor,
						}">
						<template v-if="box_stats?.mid_block.notes">{{
							box_stats?.mid_block.notes
						}}</template>
						<span
							v-else
							class="italic opacity-75 animate-pulse"
							:style="{
								color: fallbackColor + '55',
							}">
							{{ trans("No note added") }}
						</span>
					</p>
				</div>
			</div>
		</BoxStatPallet>

		<!-- Box: Order summary -->
		<BoxStatPallet class="col-span-2 border-t lg:border-t-0 border-gray-300">
			<section aria-labelledby="summary-heading" class="rounded-lg px-4 py-4 sm:px-6 lg:mt-0">
				<!-- <h2 id="summary-heading" class="text-lg font-medium">Order summary</h2> -->

				<OrderSummary :order_summary="box_stats.order_summary" :currency_code="currency?.code" />

				<!-- <div class="mt-6">
                    <button type="submit"
                        class="w-full rounded-md border border-transparent bg-indigo-600 px-4 py-3 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-50">Checkout</button>
                </div> -->
			</section>
		</BoxStatPallet>
	</div>

	<Tabs  v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

	<div class="pb-12">
		<component
            :currency="props.currency"
			:is="component"
			:data="props[currentTab as keyof typeof props]"
			:tab="currentTab"
			:updateRoute="routes.updateOrderRoute"
			:state="data?.data?.state"
			:detachRoute="attachmentRoutes?.detachRoute" 
			:fetchRoute="routes.products_list"
			:modalOpen="isModalUploadOpen"
			:action="currentAction"
			@update:tab="handleTabUpdate"/>
	</div>

	<ModalProductList v-model="isModalUploadOpen" :fetchRoute="routes.products_list" :action="currentAction" :current="currentTab"  @update:tab="handleTabUpdate" :typeModel="'purchase_order'" />

	<Modal :isOpen="isModalOpen" @onClose="closeModal">
		<div class="min-h-72 max-h-96 px-2 overflow-auto">
			<div class="text-xl font-semibold mb-2">{{ box_stats?.mid_block.notes }}'s note</div>
			<div class="relative isolate">
				<div
					v-if="noteModalValue"
					@click="() => (noteModalValue = '')"
					class="z-10 absolute top-1 right-1 text-red-400 hover:text-red-600 text-xxs cursor-pointer">
					Clear
				</div>
				<PureTextarea
					v-model="noteModalValue"
					:rows="6"
					@keydown.ctrl.enter="() => onSubmitNote()"
					maxLength="5000" />
			</div>

			<div class="flex justify-end gap-x-2 mt-3">
				<Button
					label="cancel"
					@click="
						() => ((isModalOpen = false), (noteModalValue = box_stats?.mid_block.notes))
					"
					:style="'tertiary'" />
				<Button
					label="Save"
					@click="() => onSubmitNote()"
					:loading="isSubmitNoteLoading"
					:disabled="noteModalValue == box_stats?.mid_block.notes" />
			</div>
		</div>
	</Modal>
</template>
