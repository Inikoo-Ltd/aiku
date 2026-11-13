<script setup lang="ts">
import { computed, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTruck, faShip, faFileContract, faCreditCard, faHashtag, faCalendarAlt, faInfoCircle, faPencil } from "@fal"
import PurchaseOrderDeliveryAddressDialog from "@/Components/Procurement/PurchaseOrderDeliveryAddressDialog.vue"
import { routeType } from "@/types/route"

library.add(faTruck, faShip, faFileContract, faCreditCard, faHashtag, faCalendarAlt, faInfoCircle, faPencil)

defineOptions({ inheritAttrs: false })

type FieldData = {
	type: string
	label: string
	value: unknown
	options?: { label: string; value: string }[]
	full?: boolean
	hidden?: boolean
}

type Section = {
	title: string
	fields: Record<string, FieldData>
}

const props = defineProps<{
	data: {
		blueprint: Section[]
	}
	isOwnWarehouse?: boolean
	defaultDeliveryAddress?: string | null
	followsDefault?: boolean
	deliveryAddressRoute?: routeType | null
}>()

const isEditingDeliveryAddress = ref(false)

const visibleFields = (section: Section) =>
	Object.entries(section.fields).filter(([, field]) => !field.hidden)

const fieldValue = (field: FieldData): string => {
	if (field.value === null || field.value === undefined || field.value === "") {
		return ctrans("Not set")
	}

	if (field.type === "select") {
		return (
			field.options?.find((option) => option.value === field.value)?.label ??
			String(field.value)
		)
	}

	if (typeof field.value === "string") {
		return field.value
			.replace(/<[^>]*>/g, " ")
			.replace(/\s+/g, " ")
			.trim()
	}

	return String(field.value)
}

const fieldIcons: Record<string, string> = {
	reference: "fal fa-hashtag",
	delivery_type: "fal fa-truck",
	payment_terms: "fal fa-credit-card",
	incoterm: "fal fa-ship",
	port_of_export: "fal fa-ship",
	port_of_import: "fal fa-ship",
	terms_and_conditions: "fal fa-file-contract",
	estimated_production_date: "fal fa-calendar-alt",
	estimated_receiving_date: "fal fa-calendar-alt",
}

const allFields = computed(() => props.data.blueprint.flatMap((section) => visibleFields(section)))

const filledFields = computed(() =>
	allFields.value.filter(([fieldName, field]) => !["delivery_address", "reference"].includes(fieldName) && !isEmpty(field))
)

const deliveryAddress = computed(() => {
	const value = allFields.value.find(([fieldName]) => fieldName === "delivery_address")?.[1].value

	return typeof value === "string" ? value.replace(/<br\s*\/?>/gi, "\n").replace(/<[^>]*>/g, "").trim() : ""
})

const isEmpty = (field: FieldData) =>
	field.value === null || field.value === undefined || field.value === ""
</script>

<template>
	<div>
		<div class="flex flex-col gap-2 text-sm text-gray-500">
			<div
				v-for="[fieldName, field] in filledFields"
				:key="fieldName"
				v-tooltip="field.label"
				class="flex items-start gap-3">
				<FontAwesomeIcon :icon="fieldIcons[fieldName] ?? 'fal fa-info-circle'" class="mt-1 text-gray-400" fixed-width aria-hidden="true" />
				<span class="line-clamp-2">{{ fieldValue(field) }}</span>
			</div>
			<div class="flex items-start gap-3">
				<FontAwesomeIcon v-tooltip="ctrans('Delivery address')" icon="fal fa-truck" class="mt-1 text-gray-400" fixed-width aria-hidden="true" />
				<div :class="isOwnWarehouse ? 'flex-1' : 'flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2'">
					<div v-if="isOwnWarehouse">{{ ctrans("To our warehouse") }}</div>
					<div v-else-if="deliveryAddress" class="whitespace-pre-line">{{ deliveryAddress }}</div>
					<div v-else class="italic text-gray-400">{{ ctrans("Delivery address not set") }}</div>
				</div>
				<button
					v-if="deliveryAddressRoute"
					v-tooltip="ctrans('Change delivery address')"
					type="button"
					class="mt-0.5 shrink-0 rounded px-1 text-gray-400 hover:text-[var(--app-accent)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]"
					:aria-label="ctrans('Change delivery address')"
					@click="isEditingDeliveryAddress = true">
					<FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
				</button>
			</div>
		</div>

		<PurchaseOrderDeliveryAddressDialog
			v-if="deliveryAddressRoute"
			v-model:visible="isEditingDeliveryAddress"
			:deliveryAddress="deliveryAddress || null"
			:defaultDeliveryAddress="defaultDeliveryAddress ?? null"
			:followsDefault="followsDefault ?? false"
			:updateRoute="deliveryAddressRoute" />
	</div>
</template>
