<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

interface SupplierRow {
	id: number
	supplier_code: string
	supplier_name: string
	code: string
	name: string
	cost: number | string | null
	currency_code: string | null
	state: string | null
	state_label: string | null
	availability: string | null
	availability_label: string | null
	preferred_in: string[]
	is_preferred: boolean
	is_current: boolean
	supplier_route: routeType
	product_route: routeType | null
}

const props = defineProps<{
	data: {
		data: SupplierRow[]
		preferred_by_organisation: { organisation_code: string; supplier_code: string | null; product_code: string | null }[] | null
		set_preferred: { route: routeType; organisation_id: number | null; scope: string | null } | null
	}
	tab?: string
}>()

const locale = useLocaleStore()
const savingId = ref<number | null>(null)
const result = ref<{ id: number; message: string; tone: "done" | "skipped" | "error" } | null>(null)

const setPreferred = computed(() => props.data?.set_preferred)

const makePreferred = async (row: SupplierRow) => {
	if (!setPreferred.value) {
		return
	}

	savingId.value = row.id
	result.value = null
	try {
		const response = await axios.post(route(setPreferred.value.route.name, setPreferred.value.route.parameters), {
			supplier_product_id: row.id,
			organisation_id: setPreferred.value.organisation_id,
		})
		result.value = { id: row.id, message: response.data.message, tone: response.data.updated?.length ? "done" : "skipped" }
		router.reload({ only: [props.tab ?? "suppliers"] })
	} catch (error: any) {
		const errors = error?.response?.data?.errors
		result.value = { id: row.id, message: errors ? Object.values(errors).flat().join(" ") : ctrans("The preferred supplier could not be changed."), tone: "error" }
	} finally {
		savingId.value = null
	}
}
</script>

<template>
	<div>
		<div v-if="data?.preferred_by_organisation" class="px-4 pt-4">
			<div class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Preferred supplier by organisation") }}</div>
			<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
				<div v-for="row in data.preferred_by_organisation" :key="row.organisation_code" class="rounded-md border border-gray-200 px-3 py-2">
					<div class="whitespace-nowrap text-xs font-semibold text-gray-500">{{ row.organisation_code }}</div>
					<div class="whitespace-nowrap text-sm text-gray-800">
						{{ row.supplier_code ?? "–" }}
						<span v-if="row.product_code" class="text-xs text-gray-500">{{ row.product_code }}</span>
					</div>
				</div>
			</div>
		</div>

		<Table :resource="data" :name="tab">
			<template #cell(supplier_code)="{ item }">
				<Link :href="route(item.supplier_route.name, item.supplier_route.parameters)" class="secondaryLink whitespace-nowrap">{{ item.supplier_code }}</Link>
				<div class="text-xs text-gray-500">{{ item.supplier_name }}</div>
			</template>
			<template #cell(code)="{ item }">
				<span v-if="item.is_current || !item.product_route" class="whitespace-nowrap" :class="{ 'font-semibold': item.is_current }">{{ item.code }}</span>
				<Link v-else :href="route(item.product_route.name, item.product_route.parameters)" class="secondaryLink whitespace-nowrap">{{ item.code }}</Link>
			</template>
			<template #cell(cost)="{ item }">
				<span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormatUnitCost(item.currency_code, item.cost) }}</span>
			</template>
			<template #cell(availability)="{ item }">
				<span class="whitespace-nowrap">{{ item.availability_label ?? item.state_label }}</span>
				<div v-if="item.state && item.state !== 'active'" class="text-xs text-amber-600">{{ item.state_label }}</div>
			</template>
			<template #cell(preferred)="{ item }">
				<span v-if="item.is_preferred" class="inline-flex items-center gap-1 whitespace-nowrap rounded bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
					<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
					{{ ctrans("Preferred") }}
				</span>
				<span v-else-if="!setPreferred?.scope && item.preferred_in.length" class="whitespace-nowrap text-xs text-gray-600">{{ item.preferred_in.join(", ") }}</span>
			</template>
			<template #cell(actions)="{ item }">
				<Button
					v-if="setPreferred && !item.is_preferred"
					type="tertiary"
					size="xs"
					:label="setPreferred.scope ? ctrans('Set as preferred in :organisation', { organisation: setPreferred.scope }) : ctrans('Set as preferred')"
					:loading="savingId === item.id"
					@click="makePreferred(item)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<div v-if="result?.id === item.id" class="mt-1 max-w-xs text-xs" :class="{ 'text-red-600': result.tone === 'error', 'text-amber-600': result.tone === 'skipped', 'text-green-700': result.tone === 'done' }">{{ result.message }}</div>
			</template>
		</Table>
	</div>
</template>
