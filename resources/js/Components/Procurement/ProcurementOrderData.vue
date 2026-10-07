<script setup lang="ts">
import { computed, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faTruck, faShip, faFileContract, faCreditCard, faHashtag, faCalendarAlt, faInfoCircle } from "@fal"
import FieldForm from "@/Components/Forms/FieldForm.vue"
import { routeType } from "@/types/route"

library.add(faPencil, faTruck, faShip, faFileContract, faCreditCard, faHashtag, faCalendarAlt, faInfoCircle)

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
		updateRoute: routeType
	}
	bare?: boolean
	isOwnWarehouse?: boolean
}>()

const fieldForm = ref()
const selectedSectionIndex = ref<number | null>(null)
const selectedSection = computed(() =>
	selectedSectionIndex.value === null ? null : props.data.blueprint[selectedSectionIndex.value]
)

const visibleFields = (section: Section) =>
	Object.entries(section.fields).filter(([, field]) => !field.hidden)
const hasEditableFields = (section: Section) =>
	visibleFields(section).some(([, field]) => field.type !== "readonly")

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
		<div v-if="bare" class="flex flex-col gap-2 text-sm text-gray-500">
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
			</div>
		</div>

		<dl v-else
			class="divide-y divide-gray-100"
			:class="bare ? 'text-xs' : 'mx-4 my-4 rounded-lg border border-gray-200 bg-white text-sm'">
			<div
				v-for="(section, sectionIndex) in data.blueprint"
				:key="section.title"
				class="flex items-start gap-3"
				:class="bare ? 'py-1.5' : 'px-3 py-2'">
				<dt
					class="shrink-0 pt-px font-medium uppercase tracking-wide text-gray-400"
					:class="bare ? 'w-24 text-[10px]' : 'w-32 text-xs'">
					{{ section.title }}
				</dt>
				<dd class="flex min-w-0 flex-1 flex-wrap gap-x-4 gap-y-1">
					<span v-for="[fieldName, field] in visibleFields(section)" :key="fieldName" class="min-w-0">
						<span class="text-gray-400">{{ field.label }}:</span>
						<span
							class="ml-1 whitespace-pre-line"
							:class="isEmpty(field) ? 'text-gray-300' : 'text-gray-700'"
							:title="fieldValue(field)">
							{{ isEmpty(field) ? "—" : fieldValue(field) }}
						</span>
					</span>
				</dd>
				<button
					v-if="hasEditableFields(section)"
					type="button"
					v-tooltip="ctrans('Edit')"
					:aria-label="`${ctrans('Edit')} ${section.title}`"
					class="shrink-0 rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
					@click="selectedSectionIndex = sectionIndex">
					<FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
				</button>
			</div>
		</dl>

		<Dialog
			:visible="selectedSectionIndex !== null"
			modal
			:header="selectedSection?.title"
			:style="{ width: '42rem', maxWidth: 'calc(100vw - 2rem)' }"
			:draggable="false"
			@update:visible="
				(visible) => {
					if (!visible) selectedSectionIndex = null
				}
			">
			<div v-if="selectedSection" class="flex flex-col gap-3">
				<template
					v-for="[fieldName, field] in visibleFields(selectedSection)"
					:key="fieldName">
					<dl
						v-if="field.type === 'readonly'"
						class="grid grid-cols-3 gap-4 py-2 text-sm">
						<dt class="text-gray-400">{{ field.label }}</dt>
						<dd class="col-span-2 text-gray-700">{{ fieldValue(field) }}</dd>
					</dl>
					<FieldForm
						v-else
						ref="fieldForm"
						:field="fieldName"
						:fieldData="field"
						:args="{ updateRoute: data.updateRoute }"
						:refForms="fieldForm" />
				</template>
			</div>
		</Dialog>
	</div>
</template>
