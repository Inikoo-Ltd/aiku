<script setup lang="ts">
import { computed, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil } from "@fal"
import FieldForm from "@/Components/Forms/FieldForm.vue"
import { routeType } from "@/types/route"

library.add(faPencil)

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
		return trans("Not set")
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

const isEmpty = (field: FieldData) =>
	field.value === null || field.value === undefined || field.value === ""
</script>

<template>
	<div>
		<div class="mx-auto grid max-w-6xl grid-cols-1 gap-4 p-6 md:grid-cols-2">
			<section
				v-for="(section, sectionIndex) in data.blueprint"
				:key="section.title"
				class="rounded-lg border border-gray-200 bg-white shadow-sm">
				<div
					class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3">
					<h2 class="font-semibold text-gray-700">{{ section.title }}</h2>
					<button
						v-if="hasEditableFields(section)"
						type="button"
						class="flex items-center gap-2 rounded px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700"
						@click="selectedSectionIndex = sectionIndex">
						<FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
						{{ trans("Edit") }}
					</button>
				</div>

				<dl class="divide-y divide-gray-100 px-4">
					<div
						v-for="[fieldName, field] in visibleFields(section)"
						:key="fieldName"
						class="grid grid-cols-3 gap-4 py-3 text-sm">
						<dt class="text-gray-400">{{ field.label }}</dt>
						<dd
							class="col-span-2 line-clamp-2 whitespace-pre-line text-gray-700"
							:class="isEmpty(field) ? 'italic text-gray-400' : ''"
							:title="fieldValue(field)">
							{{ fieldValue(field) }}
						</dd>
					</div>
				</dl>
			</section>
		</div>

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
