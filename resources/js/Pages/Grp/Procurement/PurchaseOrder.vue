<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import type { Component } from "vue"
import { Head, Link } from "@inertiajs/vue3"
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
import PurchaseOrderData from "@/Components/Procurement/PurchaseOrderData.vue"
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
import { faIdCardAlt, faEnvelope, faPhone, faWeight, faStickyNote, faShip, faBox, faHandHoldingBox } from "@fal"
import { faArrowCircleLeft, faArrowCircleRight, faExclamationCircle, faPencil, faShare } from "@fas"
import { faPlus } from "@far"

library.add(
	faIdCardAlt,
	faEnvelope,
	faPhone,
	faWeight,
	faStickyNote,
	faShip,
	faBox,
	faHandHoldingBox,
	faShare,
	faArrowCircleRight,
	faArrowCircleLeft,
	faExclamationCircle,
	faPencil,
	faPlus,
)

const props = defineProps < {
    title: string
    pageHead: PageHeadingTypes
    data: {
        state: string
    }
	routes: {
		updatePurchaseOrderRoute: routeType
		products_list: routeType
	}
	box_stats: {
		first_block: {
			orderer: {
                slug: string
				type: string
				name: string
            }
            delivery: {
    			type: string | null
    			incoterm: string | null
    			port_of_export: string | null
    			port_of_import: string | null
    			delivery_address: string | null
    		}
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
	tabs: {
		current: string
		navigation: {}
	}
	showcase?: {}
	items?: {}
	history?: {}
	// attachments?: {}
	// attachmentRoutes?: {}
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
		showcase: PurchaseOrderData,
		items: TablePurchaseOrderTransactions,
		// products: TableProductList,
		history: TableHistories,
		// attachments: TableAttachments,
	}

	return components[currentTab.value]
})

const ordererRoute = computed<string>(() => {
	const orderer = props.box_stats.first_block.orderer
    const slug = orderer.slug
    const type = orderer.type

	if (!slug || !type) return ""

	const organisation = route().params["organisation"]

	switch (type) {
		case "Agent":
			return route("grp.org.procurement.org_agents.show", [organisation, slug])
		case "Supplier":
			return route("grp.org.procurement.org_suppliers.show", [organisation, slug])
		default:
			return ""
	}
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
            route(props.routes.updatePurchaseOrderRoute.name, props.routes.updatePurchaseOrderRoute.parameters),
            { notes: noteModalValue.value },
            { headers: { "Content-Type": "application/json" } },
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

	<PageHeading :data="pageHead">
	    <!-- Todo: Replace with tab All supplier's products -->
		<template #button-add-products="{ action }">
			<div class="relative">
				<Button
					:key="`ActionButton${action.label}${action.style}`"
					:label="action.label"
					:tooltip="action.tooltip"
					:icon="action.icon"
					:style="action.style"
					@click="() => openModal(action)"
				/>
			</div>
		</template>
	</PageHeading>

	<!-- Purchase Order Timeline -->
	<div v-if="timelines" class="py-2 border-b border-gray-300">
		<Timeline
			:options="timelines"
			:state="props.data.state"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'"
		/>
	</div>

	<!-- Todo: Add Stock Delivery Timeline -->

	<div class="grid grid-cols-2 lg:grid-cols-4 text-gray-500 divide-x divide-gray-300 border-b border-gray-300">
	    <!-- First Block -->
		<BoxStatPallet class="p-4">
			<div class="flex flex-col gap-4">
				<!-- Supplier -->
				<div v-if="box_stats.first_block.orderer.name" class="flex items-center gap-2">
					<dt>
						<FontAwesomeIcon
							v-tooltip="trans(box_stats.first_block.orderer.type)"
							icon="fal fa-hand-holding-box"
							aria-hidden="true"
							fixed-width
						/>
					</dt>
					<dd>
						<Link v-if="ordererRoute" :href="ordererRoute" class="primaryLink">
							{{ box_stats.first_block.orderer.name }}
						</Link>
						<span v-else>{{ box_stats.first_block.orderer.name }}</span>
					</dd>
				</div>

				<!-- Delivery terms -->
				<div v-if="box_stats.first_block.delivery.type === 'container'">
					<div class="flex items-center gap-2">
						<dt>
							<FontAwesomeIcon
								v-tooltip="trans('Incoterm')"
								icon="fas fa-share"
								aria-hidden="true"
								fixed-width
							/>
						</dt>
						<dd v-if="box_stats.first_block.delivery.incoterm">{{ box_stats.first_block.delivery.incoterm }}</dd>
						<dd v-else class="flex items-center gap-1 text-red-500 text-sm italic">
		                    <FontAwesomeIcon
    							icon="fas fa-exclamation-circle"
    							aria-hidden="true"
    							fixed-width
    						/>
						    <span>{{ trans("Incoterm not set") }}</span>
						</dd>
					</div>

					<div class="flex items-center gap-2">
						<dt>
							<FontAwesomeIcon
								v-tooltip="trans('Port of export')"
								icon="fas fa-arrow-circle-right"
								aria-hidden="true"
								fixed-width
							/>
						</dt>
						<dd v-if="box_stats.first_block.delivery.port_of_export">{{ box_stats.first_block.delivery.port_of_export }}</dd>
						<dd v-else class="flex items-center gap-1 text-red-500 text-sm italic">
                            <FontAwesomeIcon
     							icon="fas fa-exclamation-circle"
     							aria-hidden="true"
     							fixed-width
      						/>
                            <span>{{ trans("Port of export not set") }}</span>
						</dd>
					</div>

					<div class="flex items-center gap-2">
						<dt>
							<FontAwesomeIcon
								v-tooltip="trans('Port of import')"
								icon="fas fa-arrow-circle-left"
								aria-hidden="true"
								fixed-width />
						</dt>
						<dd v-if="box_stats.first_block.delivery.port_of_import">{{ box_stats.first_block.delivery.port_of_import }}</dd>
						<dd v-else class="flex items-center gap-1 text-red-500 text-sm italic">
                            <FontAwesomeIcon
     							icon="fas fa-exclamation-circle"
     							aria-hidden="true"
     							fixed-width
      						/>
                            <span>{{ trans("Port of import not set") }}</span>
						</dd>
					</div>
				</div>

				<!-- Deliver to -->
				<div class="pt-2 text-sm">
					<div class="text-gray-400">{{ trans("Deliver to") }}:</div>
					<div v-if="box_stats.first_block.delivery.delivery_address" class="whitespace-pre-line">{{ box_stats.first_block.delivery.delivery_address }}</div>
					<div v-else class="flex items-center gap-1 text-red-500 italic">
                        <FontAwesomeIcon
 							icon="fas fa-exclamation-circle"
 							aria-hidden="true"
 							fixed-width
  						/>
                        <span>{{ trans("Delivery address not set") }}</span>
					</div>
				</div>
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
			:state="data?.state"
			xdetachRoute="attachmentRoutes?.detachRoute"
			xfetchRoute="routes.products_list"
			xmodalOpen="isModalUploadOpen"
			xaction="currentAction"
			@update:tab="handleTabUpdate"
		/>
	</div>

	<!-- <ModalProductList v-model="isModalUploadOpen" :fetchRoute="routes.products_list" :action="currentAction" :current="currentTab"  @update:tab="handleTabUpdate" :typeModel="'purchase_order'" /> -->

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
