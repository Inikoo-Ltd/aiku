<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faVideo } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
	action: {
		label: string
		style?: string
		tooltip?: string
		disabled?: boolean
		route: routeType
		packing_benches: { id: number; code: string }[]
	}
}>()

const visible = ref(false)
const submittingBenchId = ref<number | null>(null)
const errorMessage = ref<string | null>(null)

const open = () => {
	errorMessage.value = null
	visible.value = true
}

const startPacking = (benchId: number) => {
	router.patch(
		route(props.action.route.name, props.action.route.parameters),
		{ packing_bench: benchId },
		{
			preserveScroll: true,
			onStart: () => {
				submittingBenchId.value = benchId
				errorMessage.value = null
			},
			onSuccess: () => {
				visible.value = false
			},
			onError: (errors) => {
				errorMessage.value = errors.packing_bench ?? Object.values(errors)[0] ?? ctrans("Could not start packing, please try again")
			},
			onFinish: () => {
				submittingBenchId.value = null
			},
		}
	)
}
</script>

<template>
	<div>
		<ButtonWithLink
			v-if="!action.packing_benches?.length"
			:label="action.label"
			:style="action.style"
			v-tooltip="action.tooltip"
			:disabled="action.disabled"
			:routeTarget="action.route"
		/>
		<Button
			v-else
			:label="action.label"
			:style="action.style"
			v-tooltip="action.tooltip"
			:disabled="action.disabled"
			@click="open"
		/>

		<Dialog v-model:visible="visible" modal :header="ctrans('Which bench are you packing on?')" :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
			<div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
				<button
					v-for="bench in action.packing_benches"
					:key="bench.id"
					type="button"
					:disabled="submittingBenchId !== null"
					class="flex h-20 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-gray-50 text-2xl font-bold text-gray-800 whitespace-nowrap hover:border-[var(--app-accent)] hover:bg-[var(--app-accent-soft)] disabled:cursor-not-allowed disabled:opacity-60"
					@click="startPacking(bench.id)"
				>
					<LoadingIcon v-if="submittingBenchId === bench.id" />
					<FontAwesomeIcon v-else :icon="faVideo" class="text-base text-gray-400" fixed-width aria-hidden="true" />
					{{ bench.code }}
				</button>
			</div>
			<p v-if="errorMessage" class="mt-3 text-xs text-red-600">{{ errorMessage }}</p>

			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
						<template #icon>
							<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Dialog>
	</div>
</template>
