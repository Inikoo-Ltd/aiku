<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import { getComponent } from "@/Composables/Listing/FieldFormList"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSave as fadSave } from "@fad"
import { faSave as falSave } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(fadSave, falSave)

defineOptions({ inheritAttrs: false })

type NestedField = {
	type: string
	label?: string
	value?: unknown
	options?: unknown
}

type RouteType = {
	name: string
	parameters?: Record<string, unknown> | unknown[]
}

const props = defineProps<{
	fieldName: string
	fieldData: {
		label?: string
		fields: Record<string, NestedField>
	}
	updateRoute?: RouteType
}>()

const forms: Record<string, ReturnType<typeof useForm>> = {}
for (const [key, nested] of Object.entries(props.fieldData.fields)) {
	forms[key] = useForm({ [key]: nested.value ?? "", _method: "patch" })
}

const save = (key: string) => {
	if (!props.updateRoute) return
	forms[key].post(route(props.updateRoute.name, props.updateRoute.parameters), {
		preserveScroll: true,
	})
}

const onEnter = (event: KeyboardEvent, key: string, type: string) => {
	if (type === "select") return
	event.preventDefault()
	save(key)
}
</script>

<template>
	<div class="w-full">
		<h4
			v-if="fieldData.label"
			class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
			{{ fieldData.label }}
		</h4>

		<div class="rounded-lg border border-gray-200 divide-y divide-gray-100 px-4">
			<div
				v-for="(nested, key) in fieldData.fields"
				:key="key"
				class="py-3 sm:grid sm:grid-cols-3 sm:items-center sm:gap-4">
				<label class="text-sm font-medium text-gray-400">{{ nested.label }}</label>

				<div class="mt-1 flex items-start gap-2 sm:col-span-2 sm:mt-0">
					<div class="relative w-full" @keydown.enter="onEnter($event, key, nested.type)">
						<component
							:is="getComponent(nested.type)"
							:form="forms[key]"
							:fieldName="key"
							:fieldData="nested"
							:options="nested.options" />
					</div>

					<button
						type="button"
						class="h-9 flex-shrink-0"
						:disabled="forms[key].processing || !forms[key].isDirty"
						@click="save(key)">
						<FontAwesomeIcon
							v-if="forms[key].processing"
							icon="fad fa-spinner-third"
							class="h-8 animate-spin text-gray-400"
							aria-hidden="true" />
						<FontAwesomeIcon
							v-else-if="forms[key].isDirty"
							icon="fad fa-save"
							class="h-8"
							:style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }"
							aria-hidden="true" />
						<FontAwesomeIcon
							v-else
							icon="fal fa-save"
							class="h-8 text-gray-300"
							aria-hidden="true" />
					</button>
				</div>
			</div>
		</div>
	</div>
</template>
