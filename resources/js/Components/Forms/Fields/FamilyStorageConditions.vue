<script setup lang="ts">
import { computed, onMounted } from "vue"
import { trans } from "laravel-vue-i18n"
import { get, set, isEqual } from "lodash-es"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"

type StorageColumn = {
	value: string
	label: string
	placeholder?: string
}

type StorageRow = {
	key: string
	label: string
	placeholder: string
	value: string
}

const props = defineProps<{
	form: any
	fieldName: string
	fieldData?: {
		options?: StorageColumn[]
		[key: string]: any
	}
}>()

const defaultColumns: StorageColumn[] = [
	{
		value: "storage",
		label: trans("Storage"),
		placeholder: trans("e.g. Store in a cool, dry place away from direct sunlight and heat."),
	},
	{
		value: "shelf_life",
		label: trans("Shelf Life"),
		placeholder: trans("e.g. 24 months from date of manufacture (see batch number)."),
	},
	{
		value: "after_opening",
		label: trans("POA (After Opening)"),
		placeholder: trans("e.g. Use within 12 months of opening."),
	},
]

const columns = computed<StorageColumn[]>(() =>
	props.fieldData?.options?.length ? props.fieldData.options : defaultColumns
)

const buildRows = (): StorageRow[] => {
	const saved = get(props.form, props.fieldName)
	const savedRows: StorageRow[] = Array.isArray(saved) ? saved : []

	return columns.value.map((column) => {
		const savedRow = savedRows.find((row) => row.key === column.value)

		return {
			key: column.value,
			label: column.label,
			placeholder: column.placeholder ?? "",
			value: savedRow?.value ?? "",
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

const rows = computed<StorageRow[]>(() => get(props.form, props.fieldName) ?? [])

const error = computed(() => get(props.form, ["errors", props.fieldName]))
</script>

<template>
	<div>
		<div class="grid gap-2 md:grid-cols-1">
			<div v-for="row in rows" :key="row.key">
				<label class="mb-1 block text-xs font-medium text-gray-500">
					{{ row.label }}
				</label>

				<PureTextarea
					v-model="row.value"
					:rows="3"
					:placeholder="row.placeholder"
					:inputName="`${fieldName}_${row.key}`" />
			</div>
		</div>

		<p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
	</div>
</template>
