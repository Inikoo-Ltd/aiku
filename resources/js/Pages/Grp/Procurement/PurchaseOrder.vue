<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { computed, ref } from "vue"
import type { Component } from "vue"
import { Head } from "@inertiajs/vue3"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ModalProductList from "@/Components/Utils/ModalProductList.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import TablePurchaseOrderTransactions from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrderTransactions.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import TableProductList from "@/Components/Tables/Grp/Helpers/TableProductList.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"

import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { Timeline as TSTimeline } from "@/types/Timeline"
import { PalletDelivery } from "@/types/Pallet"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faIdCardAlt, faEnvelope, faPhone, faWeight, faStickyNote } from "@fal"
import { faPencil } from "@fas"
import { faPlus } from "@far"

library.add(faIdCardAlt, faEnvelope, faPhone, faWeight, faStickyNote, faPencil, faPlus)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	routes: {
		updatePurchaseOrderRoute: routeType
		products_list: routeType
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
	timelines: {
		[key: string]: TSTimeline
	}
	currency: {}
	data?: {
		data: PalletDelivery
	}
	tabs: {
		current: string
		navigation: {}
	}
	showcase: {}
	transactions?: {}
	history?: {}
	attachments?: {}
	attachmentRoutes?: {}
}>()

const fallbackBgColor = "#f9fafb"
const fallbackColor = "#374151"

const currentTab = ref(props.tabs.current)
const currentAction = ref(null)
const isModalOpen = ref(false)
const isModalUploadOpen = ref(false)
const isSubmitNoteLoading = ref(false)
const noteModalValue = ref(props.box_stats.mid_block.notes || "")

const component = computed(() => {
	const components: Component = {
		history: TableHistories,
		transactions: TablePurchaseOrderTransactions,
		attachments: TableAttachments,
		products: TableProductList,
	}

	return components[currentTab.value]
})

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const openModal = (action: any) => {
	currentAction.value = action
	isModalUploadOpen.value = true
}

const closeModal = () => {
	isModalOpen.value = false
	noteModalValue.value = props.box_stats.mid_block.notes || ""
}

const onSubmitNote = async () => {
	isSubmitNoteLoading.value = true

	try {
		await axios.patch(
			route(
				props.routes.updatePurchaseOrderRoute.name,
				props.routes.updatePurchaseOrderRoute.parameters
			),
			{ notes: noteModalValue.value },
			{ headers: { "Content-Type": "application/json" } }
		)
		props.box_stats.mid_block.notes = noteModalValue.value
	} catch (error) {
		notify({
			title: "Failed",
			text: "Failed to update the note, try again.",
			type: "error",
		})
	}

	isSubmitNoteLoading.value = false
	isModalOpen.value = false
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" v-if="currentTab != 'products'">
		<template #button-add-products="{ action }" >
			<div class="relative">
				<Button
					:style="action.style"
					:label="action.label"
					:icon="action.icon"
					@click="() => openModal(action)"
					:key="`ActionButton${action.label}${action.style}`"
					:tooltip="action.tooltip"
				/>
			</div>
		</template>
	</PageHeading>

	<!-- Section: Timeline -->
	<div v-if="timelines" class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
		<Timeline
			:options="timelines"
			:state="props.data?.data?.state"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'"
		/>
	</div>

	<div v-if="currentTab != 'products'" class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-300 border-b border-gray-200">
		<BoxStatPallet class="py-2 px-3" icon="fal fa-user">
			<!-- Field: Reference Number -->
			<div v-if="box_stats?.orderer.data.code" class="pl-1 flex items-center w-fit flex-none gap-x-2">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-user"
						class="text-gray-400"
						fixed-width
						aria-hidden="true"
					/>
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
						aria-hidden="true"
					/>
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
				<OrderSummary :order_summary="box_stats.order_summary" :currency_code="currency?.code" />
			</section>
		</BoxStatPallet>
	</div>

	<Tabs v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

	<div class="pb-12">
		<component
			:is="component"
			:currency="props.currency"
			:data="props[currentTab as keyof typeof props]"
			:tab="currentTab"
			:updateRoute="routes.updateOrderRoute"
			:state="data?.data?.state"
			:detachRoute="attachmentRoutes?.detachRoute"
			:fetchRoute="routes.products_list"
			:modalOpen="isModalUploadOpen"
			:action="currentAction"
			@update:tab="handleTabUpdate" />
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
