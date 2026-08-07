<script setup lang="ts">
import { computed } from "vue"
import Dialog from "primevue/dialog"
import { trans } from "laravel-vue-i18n"

interface VariableOption {
	label: string
	value: string
}

const props = defineProps<{
	show: boolean
	variables: VariableOption[]
}>()

const emit = defineEmits<{
	(e: "close"): void
	(e: "insert", value: string): void
}>()

const visible = computed({
	get: () => props.show,
	set: (value: boolean) => {
		if (!value) emit("close")
	},
})

const insertVariable = (value: string) => {
	emit("insert", value)
	emit("close")
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		:header="trans('Insert variable')"
		modal
		:style="{ width: '26rem' }"
		:breakpoints="{ '640px': '95vw' }">
		<div class="flex flex-col gap-3">
			<p class="text-sm text-gray-500">
				{{ trans('Pick a variable to place at the cursor. Its value is filled in automatically when a visitor opens the page.') }}
			</p>

			<div class="max-h-72 divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200">
				<button
					v-for="variable in variables"
					:key="variable.value"
					type="button"
					class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left transition-colors hover:bg-blue-50"
					@click="insertVariable(variable.value)">
					<span class="text-sm text-gray-700">{{ variable.label }}</span>
					<code class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-500">
						{{ variable.value }}
					</code>
				</button>

				<p v-if="!variables.length" class="px-3 py-6 text-center text-sm text-gray-400">
					{{ trans('No variable available') }}
				</p>
			</div>

			<div class="flex justify-end">
				<button
					type="button"
					class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100"
					@click="emit('close')">
					{{ trans('Cancel') }}
				</button>
			</div>
		</div>
	</Dialog>
</template>
