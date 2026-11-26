<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-20 14:10
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import ProductDialogHeader from "@/Components/Product/ProductDialogHeader.vue"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxCheck, faBoxOpen, faCheck, faExclamationTriangle, faMinus, faPlus, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type Packing = {
	quantity_packable: number
	quantity_to_check: number
	quantity_packed: number
	pack_route: routeType | null
	unpack_route?: routeType | null
}

const props = withDefaults(defineProps<{
	code: string | null
	name?: string | null
	image?: object | null
	packing: Packing
	unitsPerSko?: number | null
	canPack?: boolean
}>(), {
	unitsPerSko: null,
	name: null,
	image: null,
	canPack: true,
})

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const roundUnits = (value: number) => Math.round(Number(value ?? 0) * 1000) / 1000
const packable = computed(() => roundUnits(props.packing.quantity_packable))
const packed = computed(() => roundUnits(props.packing.quantity_packed))

const isPackDialogVisible = ref(false)
const isUnpackDialogVisible = ref(false)
const quantity = ref<number | null>(null)
const isSaving = ref(false)
const error = ref<string | null>(null)

const quantityToPack = computed(() => roundUnits(quantity.value ?? 0))
const unitsLeft = computed(() => Math.max(0, roundUnits(packable.value - quantityToPack.value)))
const canSubmitPack = computed(() => quantityToPack.value > 0 && quantityToPack.value <= packable.value && !isSaving.value)
const skosOf = (units: number) => (props.unitsPerSko && props.unitsPerSko > 0 ? locale.number(roundUnits(units / props.unitsPerSko)) : null)

function openPackDialog() {
	quantity.value = packable.value
	error.value = null
	isPackDialogVisible.value = true
}

function openUnpackDialog() {
	error.value = null
	isUnpackDialogVisible.value = true
}

function send(packingRoute: routeType, data: Record<string, number>, onSuccess: () => void) {
	router.patch(route(packingRoute.name, packingRoute.parameters), data, {
		preserveScroll: true,
		onStart: () => {
			isSaving.value = true
			error.value = null
		},
		onFinish: () => (isSaving.value = false),
		onSuccess,
		onError: (errors: Record<string, string>) => (error.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
	})
}

function submitPack() {
	if (!canSubmitPack.value || !props.packing.pack_route) {
		return
	}

	send(props.packing.pack_route, { quantity: quantityToPack.value }, () => (isPackDialogVisible.value = false))
}

function submitUnpack() {
	if (!props.packing.unpack_route || isSaving.value) {
		return
	}

	send(props.packing.unpack_route, {}, () => (isUnpackDialogVisible.value = false))
}
</script>

<template>
	<div class="inline-flex flex-wrap items-center gap-1">
		<Button
			v-if="canPack && packing.pack_route"
			:label="ctrans('Mark packed')"
			:tooltip="ctrans('Click to choose how many of the :units received units to mark as packed', { units: locale.number(packable) })"
			type="tertiary"
			size="xs"
			@click="openPackDialog"
		>
			<template #icon>
				<FontAwesomeIcon :icon="faBoxCheck" class="text-green-600" fixed-width aria-hidden="true" />
			</template>
		</Button>

		<Button
			v-if="packing.unpack_route"
			:label="ctrans('Undo packing')"
			:tooltip="ctrans('Click to take back the :units packed units not in a shipment yet', { units: locale.number(packed) })"
			type="tertiary"
			size="xs"
			@click="openUnpackDialog"
		>
			<template #icon>
				<FontAwesomeIcon :icon="faBoxOpen" class="text-amber-500" fixed-width aria-hidden="true" />
			</template>
		</Button>

		<Dialog v-model:visible="isPackDialogVisible" modal :header="ctrans('Mark :code packed', { code })" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
			<div class="space-y-3 text-sm">
				<ProductDialogHeader :code="code" :name="name" :image="image" />
				<label class="block space-y-1">
					<span class="text-gray-700">{{ ctrans("Units to pack") }} <span class="text-red-500">*</span></span>
					<InputNumber
						v-model="quantity"
						:min="0"
						:max="packable"
						:step="1"
						:maxFractionDigits="3"
						:suffix="` ${ctrans('units')}`"
						showButtons
						buttonLayout="horizontal"
						inputClass="text-center"
						:incrementButtonProps="{ 'aria-label': ctrans('One unit more') }"
						:decrementButtonProps="{ 'aria-label': ctrans('One unit less') }"
						:class="fieldFocusClass"
						fluid
						@keydown.enter="submitPack"
					>
						<template #incrementbuttonicon>
							<FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
						</template>
						<template #decrementbuttonicon>
							<FontAwesomeIcon :icon="faMinus" fixed-width aria-hidden="true" />
						</template>
					</InputNumber>
				</label>

				<p class="text-xs text-gray-500">
					<template v-if="!quantityToPack">{{ ctrans("Nothing packed, all :units units stay received", { units: locale.number(packable) }) }}</template>
					<template v-else-if="unitsLeft > 0">{{ ctrans(":left of the :units received units stay received, not packed", { left: locale.number(unitsLeft), units: locale.number(packable) }) }}</template>
					<template v-else>{{ ctrans("All :units received units", { units: locale.number(packable) }) }}</template>
					<span v-if="skosOf(quantityToPack)" class="ml-1">({{ ctrans(":skos SKO", { skos: skosOf(quantityToPack) }) }})</span>
				</p>

				<p v-if="packing.quantity_to_check > 0" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
					<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
					<span>{{ ctrans(":units of the received units are not checked yet. Once packed they can no longer be checked.", { units: locale.number(packing.quantity_to_check) }) }}</span>
				</p>

				<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
			</div>

			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isPackDialogVisible = false">
						<template #icon>
							<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="primary" full :label="ctrans('Mark :units units packed', { units: locale.number(quantityToPack) })" :loading="isSaving" :disabled="!canSubmitPack" @click="submitPack">
						<template #icon>
							<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Dialog>

		<Dialog v-model:visible="isUnpackDialogVisible" modal :header="ctrans('Undo packing :code', { code })" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
			<div class="space-y-3 text-sm">
				<ProductDialogHeader :code="code" :name="name" :image="image" />
				<p class="text-gray-700">
					{{ ctrans("The :units packed units go back to received, waiting to be packed again. Units already in a shipment stay packed.", { units: locale.number(packed) }) }}
				</p>
				<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
			</div>

			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isUnpackDialogVisible = false">
						<template #icon>
							<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="tertiary" full :label="ctrans('Undo packing')" :loading="isSaving" :disabled="isSaving" @click="submitUnpack">
						<template #icon>
							<FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Dialog>
	</div>
</template>
