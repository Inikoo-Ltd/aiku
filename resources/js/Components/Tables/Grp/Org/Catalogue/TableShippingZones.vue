<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"
import { ref, onMounted, onUnmounted } from "vue"
// import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
// import { faArrowRight, faTruck } from "@fal"

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

// Reactive state for managing which postal code info is visible
const activePostalInfo = ref<string | null>(null)

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

// Function to toggle postal code info visibility
const togglePostalInfo = (territoryId: string) => {
	if (activePostalInfo.value === territoryId) {
		activePostalInfo.value = null
	} else {
		activePostalInfo.value = territoryId
	}
}

// Function to close postal info when clicking outside
const handleClickOutside = (event: MouseEvent) => {
	const target = event.target as HTMLElement
	if (!target.closest(".postal-info-container")) {
		activePostalInfo.value = null
	}
}

// Add event listener for clicking outside
onMounted(() => {
	document.addEventListener("click", handleClickOutside)
})

onUnmounted(() => {
	document.removeEventListener("click", handleClickOutside)
})

/* function mapTerritories(territories: { country_code: string }[]) {
    return territories.map((territory) => territory.country_code)
} */
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(code)="{ item: zone }">
			<Link :href="shopRoute(zone)" class="primaryLink">
				{{ zone["code"] }}
			</Link>
		</template>
		<template #cell(name)="{ item: name }">
			<Link :href="shopRoute(name)" class="primaryLink">
				{{ name["name"] }}
			</Link>
		</template>
		<template #cell(position)="{ item: position }">
			{{ position["position"] }}
		</template>
		<template #cell(territories)="{ item: territories }">
			<div class="flex gap-1 flex-wrap">
				<div
					v-for="(item, index) in territories.territories"
					:key="index"
					class="text-xs text-gray-800 postal-info-container">
					<!-- Baris Utama: Country + Flag -->
					<div
						:class="`${
							item.included_postal_codes || item.excluded_postal_codes
								? 'bg-green-400'
								: ''
						} flex items-center gap-1 font-medium rounded p-1 relative cursor-pointer select-none`"
						@click="
							item.included_postal_codes || item.excluded_postal_codes
								? togglePostalInfo(`${territories.id}-${index}`)
								: null
						">
						<img
							class="inline-block h-[14px] w-[20px] object-cover rounded-sm"
							:src="'/flags/' + item.country_code.toLowerCase() + '.png'"
							:alt="`Bendera ${item.country_code}`"
							loading="lazy" />
						<span>{{ item.country_code }}</span>

						<!-- Exclamation Icon -->
						<div
							v-if="item.included_postal_codes || item.excluded_postal_codes"
							class="absolute -top-1 -right-1 bg-yellow-400 text-yellow-800 rounded-full w-4 h-4 flex items-center justify-center text-xs font-bold z-20">
							!
						</div>

						<!-- Postal Code Info (Clickable) -->
						<div
							v-if="
								(item.included_postal_codes || item.excluded_postal_codes) &&
								activePostalInfo === `${territories.id}-${index}`
							"
							class="absolute left-1/2 top-[30px] transform -translate-x-1/2 bg-white p-2 rounded-md z-10 shadow border transition-all before:content-[''] before:absolute before:top-[-8px] before:left-1/2 before:transform before:-translate-x-1/2 before:border-l-8 before:border-r-8 before:border-b-8 before:border-l-transparent before:border-r-transparent before:border-b-white after:content-[''] after:absolute after:top-[-9px] after:left-1/2 after:transform after:-translate-x-1/2 after:border-l-8 after:border-r-8 after:border-b-8 after:border-l-transparent after:border-r-transparent after:border-b-gray-300">
							<!-- Close button -->

							<!-- Included -->
							<div v-if="item.included_postal_codes" class="text-green-600 mb-1">
								<span class="font-semibold text-nowrap">✔ Included:</span>
								<span class="text-gray-700 font-mono">{{
									item.included_postal_codes
								}}</span>
							</div>

							<!-- Excluded -->
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
		</template>

		<template #cell(price)="{ item: price }">
			<div class="space-y-1 text-xs text-gray-800">
				<!-- TBC Case -->
				<div v-if="price.price.type === 'TBC'" class="text-gray-500 italic">
					<font-awesome-icon icon="fas fa-clock" class="text-yellow-500 mr-1" />
					Shipping price: TBC
				</div>

				<!-- Step Pricing -->
				<div v-else class="space-y-1">
					<div
						v-for="(priceStep, index) in price.price.steps"
						:key="index"
						class="grid grid-cols-[80px_100px_80px] items-center gap-2 text-xs">
						<!-- Type -->
						<div class="flex items-center gap-1 text-gray-600 text-sm">
							<font-awesome-icon
								:icon="
									price.price.type === 'Step Order Estimated Weight'
										? 'fas fa-weight'
										: 'fas fa-box'
								"
								class="text-blue-500" />
							<span>
								{{
									price.price.type === "Step Order Estimated Weight"
										? "Weight"
										: "Items"
								}}
							</span>
						</div>

						<!-- Range -->
						<div class="flex items-center gap-1 text-gray-700 text-sm">
							<span>£{{ priceStep.from }}</span>
							<font-awesome-icon icon="fas fa-arrow-right" />
							<span v-if="priceStep.to !== 'INF'">£{{ Number(priceStep.to) }}</span>
							<span v-else>∞</span>
						</div>

						<!-- Price -->
						<div
							class="font-bold text-right text-sm"
							:class="priceStep.price === 0 ? 'text-green-600' : 'text-black'">
							<span v-if="priceStep.price === 0">
								<font-awesome-icon icon="fas fa-truck" class="mr-1" />
								Free
							</span>
							<span v-else> £{{ priceStep.price }} </span>
						</div>
					</div>
				</div>
			</div>
		</template>
	</Table>
</template>
