<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-04 14:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExclamationTriangle, faPlus, faMinus } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type Stock = {
	id: number
	code: string
	name?: string | null
	image_thumbnail?: object | null
	quantity: number
	is_unchecked: boolean
	is_from_goods_receipt: boolean
}

const props = defineProps<{
	stocks: Stock[]
	organisation: string
}>()

const emit = defineEmits<{
	(e: "packed"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const quantities = ref<Record<number, number | null>>({})
const isSaving = ref(false)
const error = ref<string | null>(null)

watch(
	[visible, () => props.stocks.map((stock) => stock.id).join(",")],
	([isVisible]) => {
		if (!isVisible) {
			return
		}
		error.value = null
		quantities.value = Object.fromEntries(props.stocks.map((stock) => [stock.id, stock.quantity]))
	},
	{ immediate: true }
)

const quantityOf = (stock: Stock) => quantities.value[stock.id] ?? 0
const leftOf = (stock: Stock) => Math.max(0, Math.round((stock.quantity - quantityOf(stock)) * 1000) / 1000)

const linesToPack = computed(() => props.stocks.filter((stock) => quantityOf(stock) > 0))
const totalUnits = computed(() => linesToPack.value.reduce((sum, stock) => sum + quantityOf(stock), 0))
const uncheckedLines = computed(() => linesToPack.value.filter((stock) => stock.is_unchecked))
const isOverQuantity = computed(() => props.stocks.some((stock) => quantityOf(stock) > stock.quantity))
const canSubmit = computed(() => linesToPack.value.length > 0 && !isOverQuantity.value && !isSaving.value)

const header = computed(() =>
	props.stocks.length === 1
		? ctrans("Mark :code packed", { code: props.stocks[0]?.code })
		: ctrans("Mark :count lines packed", { count: locale.number(props.stocks.length) })
)

function submit() {
	if (!canSubmit.value) {
		return
	}

	const isSingle = linesToPack.value.length === 1
	const stock = linesToPack.value[0]

	router.patch(
		isSingle ? route("grp.org.agent.warehouse.pack", [props.organisation, stock.id]) : route("grp.org.agent.warehouse.pack_many", [props.organisation]),
		isSingle ? { quantity: quantityOf(stock) } : { stocks: linesToPack.value.map((line) => ({ id: line.id, quantity: quantityOf(line) })) },
		{
			preserveScroll: true,
			onStart: () => {
				isSaving.value = true
				error.value = null
			},
			onFinish: () => (isSaving.value = false),
			onSuccess: () => {
				visible.value = false
				emit("packed")
			},
			onError: (errors: Record<string, string>) => (error.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
		}
	)
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="header" :style="{ width: stocks.length > 1 ? '36rem' : '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div class="space-y-4 text-sm">
			<div class="max-h-[50vh] space-y-3 overflow-y-auto">
				<div v-for="stock in stocks" :key="stock.id" class="flex items-start gap-3">
					<Image :src="stock.image_thumbnail" imageCover class="h-14 w-14 shrink-0 overflow-hidden rounded border border-gray-200" />
					<div class="min-w-0 flex-1 space-y-1">
						<div>
							<span class="whitespace-nowrap font-medium text-gray-800">{{ stock.code }}</span>
							<span v-if="stock.name" class="ml-1 text-xs text-gray-500">{{ stock.name }}</span>
						</div>
						<InputNumber
							v-model="quantities[stock.id]"
							:min="0"
							:max="stock.quantity"
							:step="1"
							:maxFractionDigits="3"
							:suffix="` ${ctrans('units')}`"
							:disabled="stock.is_from_goods_receipt"
							showButtons
							buttonLayout="horizontal"
							inputClass="text-center"
							:incrementButtonProps="{ 'aria-label': ctrans('One unit more') }"
							:decrementButtonProps="{ 'aria-label': ctrans('One unit less') }"
							:class="fieldFocusClass"
							fluid
						>
							<template #incrementbuttonicon>
								<FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
							</template>
							<template #decrementbuttonicon>
								<FontAwesomeIcon :icon="faMinus" fixed-width aria-hidden="true" />
							</template>
						</InputNumber>
						<span class="block text-xs text-gray-500">
							<template v-if="stock.is_from_goods_receipt">{{ ctrans("From a goods receipt: all its :units units are packed together", { units: locale.number(stock.quantity) }) }}</template>
							<template v-else-if="!quantityOf(stock)">{{ ctrans("Not packed, stays received") }}</template>
							<template v-else-if="leftOf(stock) > 0">{{ ctrans(":left of the :units units stay received, not packed", { left: locale.number(leftOf(stock)), units: locale.number(stock.quantity) }) }}</template>
							<template v-else>{{ ctrans("All :units units", { units: locale.number(stock.quantity) }) }}</template>
						</span>
					</div>
				</div>
			</div>

			<p v-if="uncheckedLines.length" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
				<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
				<span>{{ ctransChoice(":count line is not checked yet: once packed it can no longer be checked|:count lines are not checked yet: once packed they can no longer be checked", uncheckedLines.length, { count: locale.number(uncheckedLines.length) }) }}</span>
			</p>

			<p class="text-gray-700">
				{{ ctrans("Packing :units units in :lines lines for the next container", { units: locale.number(totalUnits), lines: locale.number(linesToPack.length) }) }}
			</p>

			<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Mark packed')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
