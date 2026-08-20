<script setup lang="ts">
import { computed, onMounted } from "vue"
import { trans } from "laravel-vue-i18n"
import { get, set, isEqual } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBox, faSmoke, faTint, faFlask, faTags, faBoxOpen } from "@fal"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import Toggle from "@/Components/Pure/Toggle.vue"

library.add(faBox, faSmoke, faTint, faFlask, faTags, faBoxOpen)

type CustomizeOption = {
	value: string
	label: string
	icon: string
}

type CustomizeRow = {
	key: string
	label: string
	icon: string
	available: boolean
	moq: string
	notes: string
}

const props = defineProps<{
	form: any
	fieldName: string
	fieldData?: {
		options?: CustomizeOption[]
		[key: string]: any
	}
}>()

const defaultOptions: CustomizeOption[] = [
	{ value: "packaging", label: trans("Packaging"), icon: "fal fa-box" },
	{ value: "fragrance", label: trans("Fragrance"), icon: "fal fa-smoke" },
	{ value: "colour", label: trans("Colour"), icon: "fal fa-tint" },
	{ value: "formulation", label: trans("Formulation"), icon: "fal fa-flask" },
	{ value: "labeling", label: trans("Labeling"), icon: "fal fa-tags" },
	{ value: "pack_sizes", label: trans("Pack Sizes"), icon: "fal fa-box-open" },
]

const options = computed<CustomizeOption[]>(() =>
	props.fieldData?.options?.length ? props.fieldData.options : defaultOptions
)

const buildRows = (): CustomizeRow[] => {
	const saved = get(props.form, props.fieldName)
	const savedRows: CustomizeRow[] = Array.isArray(saved) ? saved : []

	return options.value.map((option) => {
		const savedRow = savedRows.find((row) => row.key === option.value)

		return {
			key: option.value,
			label: option.label,
			icon: option.icon,
			available: savedRow?.available ?? false,
			moq: savedRow?.moq ?? "",
			notes: savedRow?.notes ?? "",
		}
	})
}

onMounted(() => {
	const rebuilt = buildRows()

	if (isEqual(get(props.form, props.fieldName), rebuilt)) {
		return
	}

	props.form.defaults?.(props.fieldName, rebuilt)
	set(props.form, props.fieldName, rebuilt)
})

const rows = computed<CustomizeRow[]>(() => get(props.form, props.fieldName) ?? [])

const error = computed(() => get(props.form, ["errors", props.fieldName]))
</script>

<template>
	<div class="space-y-3">
		<div
			v-for="row in rows"
			:key="row.key"
			class="rounded-lg border border-gray-200 p-4"
			:class="{ 'opacity-60': !row.available }">
			<div class="flex items-center justify-between gap-4">
				<div class="flex items-center gap-3">
					<span
						class="flex h-9 w-9 items-center justify-center rounded-md bg-gray-100 text-gray-600">
						<FontAwesomeIcon :icon="row.icon" fixed-width aria-hidden="true" />
					</span>

					<span class="text-sm font-medium text-gray-700">{{ row.label }}</span>
				</div>

				<Toggle v-model="row.available" size="sm" />
			</div>

			<div v-if="row.available" class="mt-4 grid gap-3 md:grid-cols-[200px_1fr]">
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-500">
						{{ trans("MOQ") }}
					</label>

					<PureInput
						v-model="row.moq"
						:placeholder="trans('e.g. £500+')"
						:inputName="`${fieldName}_${row.key}_moq`" />
				</div>

				<div>
					<label class="mb-1 block text-xs font-medium text-gray-500">
						{{ trans("Notes") }}
					</label>

					<PureTextarea
						v-model="row.notes"
						:rows="2"
						:placeholder="trans('Describe what can be customised')"
						:inputName="`${fieldName}_${row.key}_notes`" />
				</div>
			</div>
		</div>

		<p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
	</div>
</template>
