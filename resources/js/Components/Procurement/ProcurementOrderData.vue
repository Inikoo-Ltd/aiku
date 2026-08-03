<script setup lang="ts">
import { ref } from "vue"
import FieldForm from "@/Components/Forms/FieldForm.vue"
import { routeType } from "@/types/route"

defineOptions({ inheritAttrs: false })

defineProps<{
	data: {
		blueprint: {
			title: string
			fields: Record<
				string,
				{
					type: string
					label: string
					value: any
					options?: { label: string; value: string }[]
					full?: boolean
					hidden?: boolean
				}
			>
		}[]
		updateRoute: routeType
	}
}>()

const _fieldForm = ref()
</script>

<template>
	<div class="flex flex-col gap-8 p-6 mx-auto max-w-4xl">
		<section v-for="(section, sectionIndex) in data.blueprint" :key="sectionIndex">
			<h2 class="mb-4 text-gray-700 text-lg font-semibold capitalize">
				{{ section.title }}
			</h2>

			<div>
				<template v-for="(fieldData, fieldName) in section.fields" :key="fieldName">
					<template v-if="!fieldData.hidden">
						<!-- Read-only row -->
						<dl v-if="fieldData.type === 'readonly'" class="sm:grid sm:grid-cols-3 sm:gap-4 py-3 max-w-2xl">
							<dt class="text-gray-400">{{ fieldData.label }}</dt>
							<dd class="sm:col-span-2 text-gray-700">{{ fieldData.value }}</dd>
						</dl>

						<!-- Editable row (label left, floppy save icon) -->
						<div v-else class="py-1">
							<FieldForm
								ref="_fieldForm"
								:field="fieldName"
								:fieldData="fieldData"
								:args="{ updateRoute: data.updateRoute }"
								:refForms="_fieldForm"
							/>
						</div>
					</template>
				</template>
			</div>
		</section>
	</div>
</template>
