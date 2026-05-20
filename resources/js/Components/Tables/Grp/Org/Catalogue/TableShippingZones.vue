<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import type { Links, Meta } from "@/types/Table"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWeight, faBox, faClock, faBallotCheck, faGripVertical } from "@fal"
import { faTruck } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import draggable from "vuedraggable"
import HeaderCell from '@/Components/Table/HeaderCell.vue'
import { faBars } from "@far"
import { ref, onMounted, onUnmounted, inject, computed, watch } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"

library.add(faWeight, faBox, faClock, faTruck, faGripVertical)

interface ShippingZone {
	id: number
	slug: string
	code: string
	name: string
	price: {
		type: string
		steps?: Array<{
			from: string | number
			to: string | number
			price: number
		}>
	}
	territories: Array<{
		country_code: string
		included_postal_codes?: string
		excluded_postal_codes?: string
	}>
	position: number
	created_at: string
}

interface RouteParams {
	organisation?: string
	shop?: string
	shippingZoneSchema?: string
	[key: string]: string | undefined
}

const props = defineProps<{
	data: {
		data: ShippingZone[]
		links: Links
		meta: Meta
	}
	tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)
const activePostalInfo = ref<string | null>(null)
const items = ref<ShippingZone[]>(props.data.data || [])
const isDragging = ref(false)
const draggedPosition = ref<number | null>(null)

const columns = ref([
	{ key: 'position', label: 'Position', sortable: false, hidden: false, sorted: null },
	{ key: 'code', label: 'Code', sortable: false, hidden: false, sorted: null },
	{ key: 'name', label: 'Name', sortable: false, hidden: false, sorted: null },
	{ key: 'territories', label: 'Territories', sortable: false, hidden: false, sorted: null },
	{ key: 'price', label: 'Price', sortable: false, hidden: false, sorted: null },
])

const routeParams = computed(() => {
	const params = route().params as RouteParams
	return {
		organisation: params.organisation || '',
		shop: params.shop || '',
		shippingZoneSchema: params.shippingZoneSchema || ''
	}
})

const getHeaderCell = (columnKey: string) => {
	const column = columns.value.find(col => col.key === columnKey)
	return {
		key: column?.key,
		label: column?.label,
		sortable: column?.sortable,
		hidden: column?.hidden,
		sorted: column?.sorted,
		onSort: () => { },
	}
}

watch(() => props.data.data, (newData) => {
	items.value = [...newData]
}, { deep: true })
const handleDragStart = () => {
	isDragging.value = true
}

const handleDragEnd = () => {
	isDragging.value = false
	draggedPosition.value = null
}

const handleDragChange = async (event: any) => {
	if (!event.moved) return

	items.value = items.value.map((item, index) => ({
		...item,
		position: index + 1,
	}))

	const payload = items.value.map(item => ({
		id: item.id,
		code: item.code,
		position: item.position,
	}))

	try {
		await axios.patch(
			route(
				"grp.org.shops.show.billables.shipping.show.shipping-zone.reorder",
				[
					routeParams.value.organisation,
					routeParams.value.shop,
					routeParams.value.shippingZoneSchema,
				]
			),
			{ positions: payload }
		)
	} catch (error) {
		console.error("Failed to reorder shipping zones:", error)
		notify({
			title: "Failed to Save",
			text: "Shipping zones reordered failed.",
			type: "error",
		})
	}
}

const togglePostalInfo = (territoryId: string) => {
	if (activePostalInfo.value === territoryId) {
		activePostalInfo.value = null
	} else {
		activePostalInfo.value = territoryId
	}
}

const handleClickOutside = (event: MouseEvent) => {
	const target = event.target as HTMLElement
	if (!target.closest(".postal-info-container")) {
		activePostalInfo.value = null
	}
}


const shopRoute = (zone: ShippingZone): string | null => {
	try {
		const currentRoute = route().current()
		const params = route().params as RouteParams

		switch (currentRoute) {
			case "grp.org.shops.show.billables.shipping.show":
				if (
					!params.organisation ||
					!params.shop ||
					!params.shippingZoneSchema ||
					!zone.slug
				) {
					console.warn("Missing required route parameters:", {
						organisation: params.organisation,
						shop: params.shop,
						shippingZoneSchema: params.shippingZoneSchema,
						zoneSlug: zone.slug,
					})
					return null
				}

				return route("grp.org.shops.show.billables.shipping.show.shipping-zone.show", [
					params.organisation,
					params.shop,
					params.shippingZoneSchema,
					// zone.slug,
					`${zone.slug}/edit`,
				])
			default:
				console.log("No matching route found for:", currentRoute)
				return null
		}
	} catch (error) {
		console.error("Error generating route:", error)
		return null
	}
}


onMounted(() => {
	document.addEventListener("click", handleClickOutside)
})

onUnmounted(() => {
	document.removeEventListener("click", handleClickOutside)
})

</script>

<template>
	<div class="overflow-hidden  border border-gray-200 bg-white">
		<table class="w-full divide-y divide-gray-200">
			<thead class="bg-gray-50">
				<tr class="divide-x divide-gray-200">
					<th v-for="column in columns" :key="column.key">
						<HeaderCell :cell="getHeaderCell(column.key)" :column="column" :resource="items" />
					</th>
				</tr>
			</thead>

			<component :is="draggable" v-model="items" item-key="id" tag="tbody" :animation="180" handle=".drag-handle"
				ghost-class="opacity-40" class="divide-y divide-gray-100" @start="handleDragStart" @end="handleDragEnd"
				@change="handleDragChange">
				<template #item="{ element: zone, index }">
					<tr :class="[
						'transition-colors hover:bg-gray-50',
						isDragging && draggedPosition === index
							? 'bg-gray-100'
							: ''
					]">
						<!-- Drag -->
						<td class="px-4 py-3 align-top">
							<div class="drag-handle inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-2 py-1 text-sm font-medium text-gray-600 transition hover:border-gray-300 hover:bg-gray-100 hover:text-gray-800 cursor-move"
								:v-tooltip="`Position: ${zone.position}`">
								<FontAwesomeIcon :icon="faBars" class="text-xs opacity-70" fixed-width />

								<span class="tabular-nums">
									{{ zone.position }}
								</span>
							</div>
						</td>

						<!-- Code -->
						<td class="px-4 py-3 align-top">
							<Link :href="shopRoute(zone)" class="primaryLink">
								{{ zone["code"] }}
							</Link>
						</td>

						<!-- Name -->
						<td class="px-4 py-3 align-top text-sm text-gray-700">
							{{ zone.name }}
						</td>

						<!-- Territories -->
						<td class="px-4 py-3 align-top">
							<div class="flex gap-1 flex-wrap">
								<div v-for="(item, index) in zone.territories" :key="index"
									class="text-xs text-gray-800 postal-info-container">
									<div :class="`${item.included_postal_codes || item.excluded_postal_codes
										? ''
										: ''
										} flex items-center gap-1 font-medium rounded p-1 relative cursor-pointer select-none`" @click="
											item.included_postal_codes || item.excluded_postal_codes
												? togglePostalInfo(`${zone.id}-${index}`)
												: null
											">
										<img class="inline-block h-[14px] w-[20px] object-cover rounded-sm"
											:src="'/flags/' + item.country_code.toLowerCase() + '.png'"
											:alt="`Bendera ${item.country_code}`" loading="lazy" />
										<span>{{ item.country_code }}</span>

										<div v-if="item.included_postal_codes || item.excluded_postal_codes"
											class="flex items-center gap-1">
											<div
												class=" bg-yellow-400 text-yellow-800 rounded w-5 h-5 flex items-center justify-center text-xs font-bold z-20">
												<FontAwesomeIcon :icon="faBallotCheck" fixed-width />
											</div>
											<span class="font-semibold">post codes</span>
										</div>

										<div v-if="
											(item.included_postal_codes || item.excluded_postal_codes) &&
											activePostalInfo === `${zone.id}-${index}`
										" class="absolute  left-1/2 top-[30px] transform -translate-x-1/2 bg-white p-2 rounded-md z-10 shadow border transition-all before:content-[''] before:absolute before:top-[-8px] before:left-1/2 before:transform before:-translate-x-1/2 before:border-l-8 before:border-r-8 before:border-b-8 before:border-l-transparent before:border-r-transparent before:border-b-white after:content-[''] after:absolute after:top-[-9px] after:left-1/2 after:transform after:-translate-x-1/2 after:border-l-8 after:border-r-8 after:border-b-8 after:border-l-transparent after:border-r-transparent after:border-b-gray-300">

											<div v-if="item.included_postal_codes" class="text-green-600 mb-1">
												<span class="font-semibold text-nowrap">✔ Included:</span>
												<span class="text-gray-700 font-mono">{{
													item.included_postal_codes
												}}</span>
											</div>

											<div v-if="item.excluded_postal_codes" class="text-red-600">
												<span class="font-semibold">✘ Excluded:</span>
												<span class="text-gray-700 font-mono">{{
													item.excluded_postal_codes
												}}</span>
											</div>
										</div>
									</div>
								</div>
							</div>
						</td>

						<!-- Price -->
						<td class="px-4 py-3 align-top">
							<div class="space-y-1 text-xs text-gray-800">
								<!-- TBC Case -->
								<div v-if="zone.price.type === 'TBC'" class="text-gray-500 italic">
									<font-awesome-icon icon="fal fa-clock" class="text- mr-1" />
								 	{{ ctrans('Shipping price: TBC') }}
								</div>

								<!-- Step Pricing -->
								<div v-else class="space-y-1">
									<div v-for="(priceStep, index) in zone.price.steps" :key="index"
										class="grid grid-cols-[80px_100px_80px] items-center gap-2 text-xs">
										<!-- Type -->
										<div class="flex items-center gap-1 text-gray-600 text-sm">
											<font-awesome-icon :icon="zone.price.type === 'Step Order Estimated Weight'
												? 'fal fa-weight'
												: 'fal fa-box'
												" class="opacity-70" fixed-width />
											<span>
												{{
													zone.price.type === "Step Order Estimated Weight"
														? "Weight"
														: "Items"
												}}
											</span>
										</div>

										<!-- Range -->
										<div class="flex items-center gap-1 text-gray-700 text-sm tabular-nums">
											<span>{{ locale.number(priceStep.from) }}</span>
											<font-awesome-icon icon="fas fa-arrow-right" fixed-width />
											<span v-if="priceStep.to !== 'INF'">{{ locale.number(priceStep.to) }}</span>
											<span v-else>∞</span>
										</div>

										<!-- Price -->
										<div class="font-bold text-right text-sm tabular-nums"
											:class="priceStep.price === 0 ? 'text-green-600' : ''">
											<span v-if="priceStep.price === 0">
												Free
											</span>
											<span v-else-if="typeof priceStep.price == 'number'"> {{
												locale.currencyFormat(zone.currency_code, priceStep.price) }} </span>
											<span v-else> {{ priceStep.price }} </span>
										</div>
									</div>
								</div>
							</div>
						</td>
					</tr>
				</template>
			</component>
		</table>
	</div>
</template>
