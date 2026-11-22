<script setup lang="ts">
import { computed, ref, watch } from "vue"
import Dialog from "primevue/dialog"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExclamationTriangle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

const props = defineProps<{
	message: string | null
	isSaving?: boolean
}>()

const emits = defineEmits<{
	(e: "confirm"): void
	(e: "cancel"): void
}>()

const localeStore = useLocaleStore()
const isAcknowledged = ref(false)

const countWarnings = computed<{ location_code: string; current_stock: number; new_count: number; value_change: number; currency: string; largest_purchase: number; monthly_usage: number }[]>(() => {
	try {
		return props.message ? JSON.parse(props.message) : []
	} catch {
		return []
	}
})

watch(() => props.message, () => {
	isAcknowledged.value = false
})
</script>

<template>
	<Dialog :visible="!!message" modal :header="ctrans('Check this count before saving')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false" @update:visible="(visible) => !visible && emits('cancel')">
		<div class="space-y-3 text-sm">
			<p class="flex gap-2 text-amber-700">
				<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
				{{ ctrans("This count looks implausible for this SKO. A wrong count changes the stock value in the accounts.") }}
			</p>
			<dl v-for="countWarning in countWarnings" :key="countWarning.location_code" class="grid grid-cols-2 gap-x-3 gap-y-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 tabular-nums">
				<dt class="text-gray-500">{{ ctrans("Location") }}</dt>
				<dd class="whitespace-nowrap font-semibold">{{ countWarning.location_code }}</dd>
				<dt class="text-gray-500">{{ ctrans("Current stock") }}</dt>
				<dd>{{ localeStore.number(countWarning.current_stock) }}</dd>
				<dt class="text-gray-500">{{ ctrans("New count") }}</dt>
				<dd class="font-semibold">{{ localeStore.number(countWarning.new_count) }}</dd>
				<dt class="text-gray-500">{{ ctrans("Change in value") }}</dt>
				<dd class="font-semibold">{{ localeStore.currencyFormat(countWarning.currency, countWarning.value_change) }}</dd>
				<dt class="text-gray-500">{{ ctrans("Largest purchase ever") }}</dt>
				<dd>{{ localeStore.number(countWarning.largest_purchase) }}</dd>
				<dt class="text-gray-500">{{ ctrans("Typical monthly usage") }}</dt>
				<dd>{{ localeStore.number(countWarning.monthly_usage) }}</dd>
			</dl>
			<label class="flex cursor-pointer items-center gap-2">
				<Checkbox v-model="isAcknowledged" binary input-id="count-warning-acknowledged" />
				<span>{{ ctrans("I know what I am doing") }} <span class="text-red-500">*</span></span>
			</label>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="emits('cancel')">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Save count')" :loading="isSaving" :disabled="!isAcknowledged" @click="emits('confirm')">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
