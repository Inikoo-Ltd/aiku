<script setup lang="ts">
import { computed, ref, watch } from "vue"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import ColorPicker from "primevue/colorpicker"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAlignLeft, faAlignCenter, faAlignRight } from "@far"
import { faCheck, faTimes, faUndo } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import {
	type CtaButtonAlignment,
	type CtaButtonAttributes,
	type CtaButtonShape,
	type CtaButtonSize,
	type CtaButtonTarget,
	ctaButtonStyle,
	normaliseCtaButtonAttributes,
	safeCtaButtonHref,
} from "@/Components/Forms/Fields/BubleTextEditor/CtaButton/ctaButtonLayout"

const props = defineProps<{
	show: boolean
	button: CtaButtonAttributes
	isNew: boolean
}>()

const emit = defineEmits<{
	(e: "close"): void
	(e: "update", value: CtaButtonAttributes): void
}>()

const visible = ref(props.show)
const form = ref<CtaButtonAttributes>({ ...props.button })
const linkError = ref<string | null>(null)
const labelError = ref<string | null>(null)

watch(
	() => props.show,
	(isShown) => (visible.value = isShown),
)

const targetOptions: Array<{ label: string, value: CtaButtonTarget }> = [
	{ label: ctrans("Same tab"), value: "_self" },
	{ label: ctrans("New tab"), value: "_blank" },
]

const alignmentOptions: Array<{ label: string, value: CtaButtonAlignment, icon: any }> = [
	{ label: ctrans("Left"), value: "left", icon: faAlignLeft },
	{ label: ctrans("Centre"), value: "center", icon: faAlignCenter },
	{ label: ctrans("Right"), value: "right", icon: faAlignRight },
]

const sizeOptions: Array<{ label: string, value: CtaButtonSize }> = [
	{ label: ctrans("Small"), value: "small" },
	{ label: ctrans("Medium"), value: "medium" },
	{ label: ctrans("Large"), value: "large" },
]

const shapeOptions: Array<{ label: string, value: CtaButtonShape }> = [
	{ label: ctrans("Square"), value: "square" },
	{ label: ctrans("Rounded"), value: "rounded" },
	{ label: ctrans("Pill"), value: "pill" },
]

const colorPickerModel = (field: "backgroundColor" | "textColor") =>
	computed<string>({
		get: () => (form.value[field] ?? "").replace("#", ""),
		set: (hex) => (form.value[field] = hex ? `#${hex.replace("#", "")}` : null),
	})

const backgroundColorModel = colorPickerModel("backgroundColor")
const textColorModel = colorPickerModel("textColor")

const previewStyle = computed(() => ctaButtonStyle(normaliseCtaButtonAttributes(form.value)))

function closeDialog() {
	visible.value = false
	emit("close")
}

function save() {
	labelError.value = form.value.label.trim() ? null : ctrans("Write the text shown on the button.")
	linkError.value = safeCtaButtonHref(form.value.href)
		? null
		: ctrans("Enter a web address (https://…) or a page path such as /christmas.")

	if (labelError.value || linkError.value) {
		return
	}

	emit("update", normaliseCtaButtonAttributes(form.value))
	closeDialog()
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		:header="isNew ? ctrans('Add button') : ctrans('Button settings')"
		modal
		class="w-full max-w-lg"
		@hide="closeDialog"
	>
		<form @submit.prevent="save" class="flex flex-col space-y-5">
			<div class="flex min-h-20 items-center justify-center rounded-md bg-gray-50 p-4">
				<span :style="previewStyle">{{ form.label || ctrans("Buy now") }}</span>
			</div>

			<div>
				<label for="input-cta-button-label" class="mb-2 block select-none text-sm text-gray-600">
					{{ ctrans("Button text") }} <span class="text-red-500">*</span>
				</label>
				<InputText id="input-cta-button-label" v-model="form.label" class="w-full" :placeholder="ctrans('Buy now')" />
				<p v-if="labelError" class="mt-1 text-xs text-red-600">{{ labelError }}</p>
			</div>

			<div>
				<label for="input-cta-button-link" class="mb-2 block select-none text-sm text-gray-600">
					{{ ctrans("Link") }} <span class="text-red-500">*</span>
				</label>
				<InputText id="input-cta-button-link" v-model="form.href" class="w-full" placeholder="/christmas" />
				<p v-if="linkError" class="mt-1 text-xs text-red-600">{{ linkError }}</p>
				<p v-else class="mt-1 text-xs text-gray-500">
					{{ ctrans("A page of this website (/christmas) or a full web address (https://…).") }}
				</p>
				<div class="mt-2">
					<SegmentedToggle v-model="form.target" :options="targetOptions" :ariaLabel="ctrans('Open link in')" />
				</div>
			</div>

			<div class="grid gap-5 sm:grid-cols-2">
				<div>
					<div class="mb-2 select-none text-sm text-gray-600">{{ ctrans("Alignment") }}</div>
					<SegmentedToggle v-model="form.alignment" :options="alignmentOptions" :ariaLabel="ctrans('Alignment')" iconOnly />
				</div>
				<div>
					<div class="mb-2 select-none text-sm text-gray-600">{{ ctrans("Size") }}</div>
					<SegmentedToggle v-model="form.size" :options="sizeOptions" :ariaLabel="ctrans('Size')" />
				</div>
			</div>

			<div>
				<div class="mb-2 select-none text-sm text-gray-600">{{ ctrans("Shape") }}</div>
				<SegmentedToggle v-model="form.shape" :options="shapeOptions" :ariaLabel="ctrans('Shape')" />
			</div>

			<div class="grid gap-5 sm:grid-cols-2">
				<div v-for="colorField in [
					{ key: 'backgroundColor', label: ctrans('Button colour'), model: backgroundColorModel },
					{ key: 'textColor', label: ctrans('Text colour'), model: textColorModel },
				]" :key="colorField.key">
					<div class="mb-2 select-none text-sm text-gray-600">{{ colorField.label }}</div>
					<div class="flex items-center gap-2">
						<ColorPicker v-model="colorField.model.value" format="hex" />
						<span class="text-sm text-gray-700">
							{{ form[colorField.key] ?? ctrans("Website theme") }}
						</span>
						<button
							v-if="form[colorField.key]"
							type="button"
							v-tooltip="ctrans('Use the website theme colour')"
							:aria-label="ctrans('Use the website theme colour')"
							class="inline-flex h-7 w-7 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-600"
							@click="form[colorField.key] = null"
						>
							<FontAwesomeIcon :icon="faUndo" fixed-width aria-hidden="true" />
						</button>
					</div>
				</div>
			</div>

			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="closeDialog">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full nativeType="submit" :label="isNew ? ctrans('Add button') : ctrans('Apply')">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>
	</Dialog>
</template>
