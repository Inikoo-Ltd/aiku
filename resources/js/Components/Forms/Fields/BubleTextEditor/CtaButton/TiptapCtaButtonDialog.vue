<script setup lang="ts">
import { computed, ref, watch } from "vue"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import ColorPicker from "primevue/colorpicker"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAlignLeft, faAlignCenter, faAlignRight } from "@far"
import { faCheck, faTimes, faUndo } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import SelectQuery from "@/Components/SelectQuery.vue"
import { ctrans } from "@/Composables/useTrans"
import {
	type CtaButtonAlignment,
	type CtaButtonAttributes,
	type CtaButtonLinkType,
	type CtaButtonSeoRel,
	type CtaButtonShape,
	type CtaButtonSize,
	type CtaButtonTarget,
	ctaButtonStyle,
	normaliseCtaButtonAttributes,
	safeCtaButtonHref,
} from "@/Components/Forms/Fields/BubleTextEditor/CtaButton/ctaButtonLayout"
import { buildLinkHref, parseLinkHref } from "@/Components/Forms/Fields/BubleTextEditor/linkHref"
import { resolveInternalLinkRoute } from "@/Components/Forms/Fields/BubleTextEditor/internalLinkRoute"

const props = defineProps<{
	show: boolean
	button: CtaButtonAttributes
	isNew: boolean
	routeGetInternalLink?: routeType
}>()

const emit = defineEmits<{
	(e: "close"): void
	(e: "update", value: CtaButtonAttributes): void
}>()

const internalLinkRoute = resolveInternalLinkRoute(props.routeGetInternalLink)

const initialLinkType = (): CtaButtonLinkType => {
	if (props.button.linkType === "internal" && !internalLinkRoute) {
		return "web"
	}
	if (props.isNew && !props.button.href && internalLinkRoute) {
		return "internal"
	}

	return props.button.linkType
}

const visible = ref(props.show)
const form = ref<CtaButtonAttributes>({ ...props.button, linkType: initialLinkType() })
const contactFields = ref(parseLinkHref(props.button.href))
const selectedWebpage = ref(
	props.button.linkType === "internal" && props.button.href ? { id: props.button.webpageId, href: props.button.href } : null,
)
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

const linkTypeOptions: Array<{ label: string, value: CtaButtonLinkType }> = [
	...(internalLinkRoute ? [{ label: ctrans("Page of this website"), value: "internal" as CtaButtonLinkType }] : []),
	{ label: ctrans("Web address"), value: "web" },
	{ label: ctrans("Send email"), value: "email" },
	{ label: ctrans("Make a call"), value: "phone" },
	{ label: ctrans("Send SMS"), value: "sms" },
]

const seoRelOptions: Array<{ label: string, value: CtaButtonSeoRel }> = [
	{ label: ctrans("Default"), value: null },
	{ label: ctrans("Follow"), value: "follow" },
	{ label: ctrans("No follow"), value: "nofollow" },
]

const isPageLink = computed(() => form.value.linkType === "internal" || form.value.linkType === "web")

watch(
	() => form.value.linkType,
	() => (linkError.value = null),
)

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

function selectWebpage(webpage: { id?: string | number, href?: string } | null) {
	selectedWebpage.value = webpage
	form.value.href = webpage?.href ?? ""
	form.value.webpageId = webpage?.id ? String(webpage.id) : null
}

const linkHref = (): string => {
	if (isPageLink.value) {
		return form.value.href
	}

	return buildLinkHref({ ...contactFields.value, type: form.value.linkType as "email" | "phone" | "sms" })
}

const missingLinkMessage = (): string => {
	switch (form.value.linkType) {
		case "internal":
			return ctrans("Choose the page the button opens.")
		case "email":
			return ctrans("Enter the email address.")
		case "phone":
		case "sms":
			return ctrans("Enter the phone number.")
		default:
			return ctrans("Enter a web address (https://…) or a page path such as /christmas.")
	}
}

function closeDialog() {
	visible.value = false
	emit("close")
}

function save() {
	const href = linkHref()
	labelError.value = form.value.label.trim() ? null : ctrans("Write the text shown on the button.")
	linkError.value = safeCtaButtonHref(href) ? null : missingLinkMessage()

	if (labelError.value || linkError.value) {
		return
	}

	emit("update", normaliseCtaButtonAttributes({
		...form.value,
		href,
		target: isPageLink.value ? form.value.target : "_self",
		seoRel: isPageLink.value ? form.value.seoRel : null,
	}))
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

			<div class="flex flex-col gap-y-3">
				<div>
					<label for="input-cta-button-link-type" class="mb-2 block select-none text-sm text-gray-600">
						{{ ctrans("Link type") }}
					</label>
					<Select v-model="form.linkType" inputId="input-cta-button-link-type" :options="linkTypeOptions"
						optionLabel="label" optionValue="value" class="w-full" />
				</div>

				<div v-if="form.linkType === 'internal' && internalLinkRoute">
					<div class="mb-2 select-none text-sm text-gray-600">
						{{ ctrans("Page") }} <span class="text-red-500">*</span>
					</div>
					<SelectQuery :urlRoute="internalLinkRoute" :object="true" fieldName="href" :value="selectedWebpage"
						:closeOnSelect="true" :searchable="true" label="href" :canClear="true" :clearOnSearch="true"
						:onChange="selectWebpage">
						<template #singlelabel="{ value }">
							<div class="w-full truncate px-2 text-gray-800">{{ value?.path || value?.href }}</div>
						</template>
						<template #option="{ option }">
							<div class="w-full truncate px-2 text-gray-800">{{ option?.path || option?.href }}</div>
						</template>
					</SelectQuery>
				</div>

				<div v-else-if="form.linkType === 'web'">
					<label for="input-cta-button-link" class="mb-2 block select-none text-sm text-gray-600">
						{{ ctrans("Web address") }} <span class="text-red-500">*</span>
					</label>
					<InputText id="input-cta-button-link" v-model="form.href" class="w-full" placeholder="https://" />
					<p class="mt-1 text-xs text-gray-500">
						{{ ctrans("A page of this website (/christmas) or a full web address (https://…).") }}
					</p>
				</div>

				<template v-else-if="form.linkType === 'email'">
					<div>
						<label for="input-cta-button-email" class="mb-2 block select-none text-sm text-gray-600">
							{{ ctrans("Email address") }} <span class="text-red-500">*</span>
						</label>
						<InputText id="input-cta-button-email" v-model="contactFields.email" type="email" class="w-full" placeholder="name@example.com" />
					</div>
					<div class="grid gap-3 sm:grid-cols-2">
						<div>
							<label for="input-cta-button-subject" class="mb-2 block select-none text-sm text-gray-600">{{ ctrans("Subject") }}</label>
							<InputText id="input-cta-button-subject" v-model="contactFields.subject" class="w-full" />
						</div>
						<div>
							<label for="input-cta-button-body" class="mb-2 block select-none text-sm text-gray-600">{{ ctrans("Message") }}</label>
							<InputText id="input-cta-button-body" v-model="contactFields.body" class="w-full" />
						</div>
					</div>
				</template>

				<template v-else>
					<div>
						<label for="input-cta-button-phone" class="mb-2 block select-none text-sm text-gray-600">
							{{ ctrans("Phone number") }} <span class="text-red-500">*</span>
						</label>
						<InputText id="input-cta-button-phone" v-model="contactFields.phone" type="tel" class="w-full" placeholder="+44 20 1234 5678" />
					</div>
					<div v-if="form.linkType === 'sms'">
						<label for="input-cta-button-sms-body" class="mb-2 block select-none text-sm text-gray-600">{{ ctrans("Message") }}</label>
						<InputText id="input-cta-button-sms-body" v-model="contactFields.body" class="w-full" />
					</div>
				</template>

				<p v-if="linkError" class="text-xs text-red-600">{{ linkError }}</p>

				<div v-if="isPageLink" class="grid gap-3 sm:grid-cols-2">
					<div>
						<div class="mb-2 select-none text-sm text-gray-600">{{ ctrans("Open link in") }}</div>
						<SegmentedToggle v-model="form.target" :options="targetOptions" :ariaLabel="ctrans('Open link in')" />
					</div>
					<div>
						<label for="input-cta-button-seo-rel" class="mb-2 block select-none text-sm text-gray-600">
							{{ ctrans("SEO (rel attribute)") }}
						</label>
						<Select v-model="form.seoRel" inputId="input-cta-button-seo-rel" :options="seoRelOptions"
							optionLabel="label" optionValue="value" class="w-full" />
					</div>
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
