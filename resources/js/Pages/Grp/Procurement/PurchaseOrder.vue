<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import type { Component } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import PurchaseOrderData from "@/Components/Procurement/PurchaseOrderData.vue"
import TablePurchaseOrderTransactions from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrderTransactions.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"

import { useLocaleStore } from "@/Stores/locale"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"

import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { Timeline as TSTimeline } from "@/types/Timeline"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faIdCardAlt, faEnvelope, faPhone, faWeight, faStickyNote, faShip, faBox, faHandHoldingBox } from "@fal"
import { faArrowCircleDown, faArrowCircleLeft, faArrowCircleRight, faBars, faExclamationCircle, faInventory, faPencil, faShare } from "@fas"
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
    faArrowCircleDown,
	faArrowCircleRight,
	faArrowCircleLeft,
	faExclamationCircle,
	faPencil,
    faPlus,
    faInventory,
    faBars
)

const props = defineProps < {
    title: string
    pageHead: PageHeadingTypes
    data: {
        data: {
            state: string
            state_label: string
        }
    }
   	timelines: {
		[key: string]: TSTimeline
	}
    tabs: {
        current: string
        navigation: {}
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
        second_block: {
            state: string
            total_items: number
            weight: number | null
            volume: number | null
            is_weight_partial: boolean
            is_volume_partial: boolean
		}
        third_block: {
            currency: string | null
            org_currency: string | null
            org_exchange: number | string | null
            items: number | string
            extra: number | string
            total: number | string
        }
	}
	showcase?: {}
	items?: {}
	history?: {}
}>()

const locale = useLocaleStore()

const metrics = computed(() => {
	const { weight, volume, is_weight_partial, is_volume_partial } = props.box_stats.second_block

	return [
		{
			key: "weight",
			isUnknown: weight === null,
			showMark: weight === null || is_weight_partial,
			text: weight === null ? trans("Unknown weight") : `${locale.number(weight)}Kg`,
			tooltip: weight === null ? trans("No item has weight data") : trans("Some items have unknown weight"),
		},
		{
			key: "volume",
			isUnknown: volume === null,
			showMark: volume === null || is_volume_partial,
			text: volume === null ? trans("Unknown CBM") : `${locale.number(volume)} m³`,
			tooltip: volume === null ? trans("No item has CBM data") : trans("Some items have unknown CBM"),
		},
	]
})

const exchangeRate = computed(() => {
	const { org_exchange } = props.box_stats.third_block
	const rate = Number(org_exchange)

	return rate ? 1 / rate : null
})

const costBlocks = computed(() => {
	const { currency, org_currency, org_exchange, items, extra, total } = props.box_stats.third_block
	const rate = Number(org_exchange) || 1

	const buildRows = (code: string | null, factor: number) => [
		{ label: trans("Items"), value: locale.currencyFormat(code ?? "", Number(items) * factor) },
		{ label: trans("Extra costs"), value: locale.currencyFormat(code ?? "", Number(extra) * factor) },
		{ label: trans("Total"), value: locale.currencyFormat(code ?? "", Number(total) * factor), isTotal: true },
	]

	const supplierBlock = {
		key: "supplier",
		title: `${trans("Supplier invoice currency")} ${currency ?? ""}`.trim(),
		rows: buildRows(currency, 1),
	}

	if (!org_currency || org_currency === currency) {
		return [supplierBlock]
	}

	const rateLabel = exchangeRate.value === null
		? ""
		: `1 ${org_currency} = ${exchangeRate.value.toLocaleString(locale.locale_iso ?? "en", { maximumFractionDigits: 5 })} ${currency ?? ""}`.trim()

	return [
		supplierBlock,
		{
			key: "org",
			title: rateLabel,
			rows: buildRows(org_currency, rate),
		},
	]
})

const currentTab = ref(props.tabs.current)

const component = computed(() => {
	const components: Component = {
		showcase: PurchaseOrderData,
		items: TablePurchaseOrderTransactions,
		history: TableHistories,
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
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<!-- Purchase Order Timeline -->
	<div v-if="timelines" class="py-2 border-b border-gray-300">
		<Timeline
			:options="timelines"
			:state="props.data.data.state"
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
								fixed-width
							/>
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
					<div v-if="box_stats.first_block.delivery.delivery_address" class="text-xs whitespace-pre-line">{{ box_stats.first_block.delivery.delivery_address }}</div>
					<div v-else class="flex items-center gap-1 text-red-500 text-xs italic">
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

		<!-- Second Block -->
		<BoxStatPallet class="p-4">
            <div class="flex justify-center">
                {{ box_stats.second_block.state }}
            </div>

            <hr class="my-1 border-t border-gray-300" />

            <div class="flex justify-center gap-4">
                <div class="flex items-center">
                    <FontAwesomeIcon
                        v-tooltip="trans('Items')"
        				icon="fas fa-bars"
        				aria-hidden="true"
        				fixed-width
    				/>
                    <span>{{ box_stats.second_block.total_items }}</span>
                </div>

                <div class="flex items-center text-gray-300">
                    <FontAwesomeIcon
        				icon="fas fa-arrow-circle-down"
        				aria-hidden="true"
        				fixed-width
    				/>
                    <span>-</span>
                </div>

                <div class="flex items-center text-gray-300">
                    <FontAwesomeIcon
        				icon="fas fa-inventory"
        				aria-hidden="true"
        				fixed-width
    				/>
                    <span>-</span>
                </div>
            </div>

            <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                <div
                    v-for="metric in metrics"
                    :key="metric.key"
                    class="flex items-center justify-center gap-1"
                    :class="metric.isUnknown ? 'italic text-red-500' : ''"
                >
                    <FontAwesomeIcon
                        v-if="metric.showMark"
                        v-tooltip="metric.tooltip"
                        icon="fas fa-exclamation-circle"
                        :class="metric.isUnknown ? 'text-red-500' : 'text-orange-500'"
                        aria-hidden="true"
                        fixed-width
                    />
                    <span>{{ metric.text }}</span>
                </div>
            </div>
		</BoxStatPallet>

		<BoxStatPallet v-for="block in costBlocks" :key="block.key" class="p-4">
			<div class="flex justify-center text-center">
				{{ block.title }}
			</div>

			<hr class="my-1 border-t border-gray-300" />

			<div class="mt-2 space-y-1 text-sm">
				<div
					v-for="row in block.rows"
					:key="row.label"
					class="flex items-center justify-between gap-4"
					:class="row.isTotal ? 'font-semibold text-gray-700' : ''"
				>
					<span>{{ row.label }}</span>
					<span>{{ row.value }}</span>
				</div>
			</div>
		</BoxStatPallet>

		<BoxStatPallet v-for="n in (2 - costBlocks.length)" :key="`cost-empty-${n}`" class="p-4" />
	</div>

	<Tabs v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

	<div class="pb-12">
		<component
			:is="component"
			:data="props[currentTab as keyof typeof props]"
			:tab="currentTab"
			:state="data.data.state"
			:updateRoute="routes.updateOrderRoute"
			@update:tab="handleTabUpdate"
		/>
	</div>
</template>
