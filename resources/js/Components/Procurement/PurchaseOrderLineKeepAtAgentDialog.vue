<!--
    * Author: Raul Perusquia <raul@inikoo.com>
    * Created: Wed, 25 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
    * Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
	code: string
	keepAtAgent: {
		quantity: number
		min_note_length: number
		route: routeType
	}
}>()

const visible = defineModel<boolean>("visible", { default: false })

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"

const quantity = ref<number | null>(null)
const note = ref("")
const isSaving = ref(false)
const error = ref<string | null>(null)

watch(
	visible,
	(isVisible) => {
		if (!isVisible) {
			return
		}
		quantity.value = props.keepAtAgent.quantity
		note.value = ""
		error.value = null
	},
	{ immediate: true }
)

const canSubmit = computed(
	() => !isSaving.value
		&& (quantity.value ?? 0) > 0
		&& (quantity.value ?? 0) <= props.keepAtAgent.quantity
		&& note.value.trim().length >= props.keepAtAgent.min_note_length
)

function submit() {
	if (!canSubmit.value) {
		return
	}

	router.post(
		route(props.keepAtAgent.route.name, props.keepAtAgent.route.parameters),
		{ quantity: quantity.value, note: note.value.trim() },
		{
			preserveScroll: true,
			onStart: () => {
				isSaving.value = true
				error.value = null
			},
			onFinish: () => (isSaving.value = false),
			onSuccess: () => (visible.value = false),
			onError: (errors: Record<string, string>) => (error.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
		}
	)
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="ctrans('Keep rest of :code at agent', { code })" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div class="space-y-4 text-sm">
			<p class="text-gray-600">{{ ctrans("These units stay at the agent. The order can finish without them, and they can still go in a later shipment.") }}</p>

			<label class="block space-y-1">
				<span class="text-xs font-medium text-gray-700">{{ ctrans("Units") }} <span class="text-red-500">*</span></span>
				<InputNumber v-model="quantity" :min="0" :max="keepAtAgent.quantity" :maxFractionDigits="3" :class="fieldFocusClass" fluid />
			</label>

			<label class="block space-y-1">
				<span class="text-xs font-medium text-gray-700">{{ ctrans("Why?") }} <span class="text-red-500">*</span></span>
				<Textarea v-model="note" rows="2" autoResize :class="fieldFocusClass" fluid />
			</label>

			<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Keep at agent')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
