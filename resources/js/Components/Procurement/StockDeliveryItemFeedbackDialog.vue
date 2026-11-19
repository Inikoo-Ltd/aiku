<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router, useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import FileUpload from "primevue/fileupload"
import SelectButton from "primevue/selectbutton"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperPlane, faTimes, faTrashAlt } from "@fal"

export interface ItemFeedbackOptions {
	sent?: boolean
	issues: { value: string; label: string }[]
	max_photos?: number
	item_store_route?: routeType
}

export interface ItemFeedbackLine {
	id: number
	code: string | null
	description?: string | null
	supplier_code?: string | null
	feedback?: { id: number; issue: string; issue_label: string; comment: string | null; is_mine: boolean } | null
}

const props = defineProps<{
	options: ItemFeedbackOptions
	line: ItemFeedbackLine | null
}>()

const emit = defineEmits<{
	(e: "saved"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const form = useForm<{ issue: string | null; comment: string; photos: File[] }>({
	issue: null,
	comment: "",
	photos: [],
})

const deleting = ref(false)
const deleteError = ref<string | null>(null)
const existing = computed(() => props.line?.feedback ?? null)
const isReadonly = computed(() => !!existing.value && !existing.value.is_mine)
const needsComment = computed(() => form.issue === "other")
const canSubmit = computed(() => !isReadonly.value && !!form.issue && (!needsComment.value || !!form.comment.trim()))

watch(visible, (isVisible) => {
	if (!isVisible) {
		return
	}
	form.clearErrors()
	deleteError.value = null
	form.issue = existing.value?.issue ?? null
	form.comment = existing.value?.comment ?? ""
	form.photos = []
})

const submit = () => {
	if (!props.line || !canSubmit.value) {
		return
	}
	const options = {
		preserveScroll: true,
		onSuccess: () => {
			visible.value = false
			emit("saved")
		},
	}
	if (existing.value) {
		form.transform((data) => ({ issue: data.issue, comment: data.comment })).patch(route("grp.models.stock-delivery-item-feedback.update", { stockDeliveryItemFeedback: existing.value.id }), options)
		return
	}
	const target = props.options.item_store_route
	if (!target) {
		return
	}
	form.transform((data) => data).post(route(target.name, { ...target.parameters, stockDeliveryItem: props.line.id }), { ...options, forceFormData: true })
}

const remove = () => {
	if (!existing.value) {
		return
	}
	deleting.value = true
	deleteError.value = null
	router.delete(route("grp.models.stock-delivery-item-feedback.delete", { stockDeliveryItemFeedback: existing.value.id }), {
		preserveScroll: true,
		onSuccess: () => {
			visible.value = false
			emit("saved")
		},
		onError: (errors) => (deleteError.value = Object.values(errors)[0] ?? ctrans("The note could not be removed")),
		onFinish: () => (deleting.value = false),
	})
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="existing ? ctrans('Item feedback') : ctrans('Report a problem with this item')" :style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div v-if="line" class="space-y-4 text-sm">
			<div class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
				<span class="whitespace-nowrap font-medium text-gray-800">{{ line.code }}</span>
				<span v-if="line.description" class="ml-1">{{ line.description }}</span>
				<div v-if="line.supplier_code" class="mt-0.5">
					{{ ctrans("Goes to supplier") }} <span class="whitespace-nowrap font-medium text-gray-800">{{ line.supplier_code }}</span>
				</div>
			</div>

			<div class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("What was wrong?") }} <span class="text-red-500">*</span></span>
				<SelectButton v-model="form.issue" :options="options.issues" optionLabel="label" optionValue="value" :allowEmpty="false" :disabled="isReadonly" class="feedback-issues" :aria-label="ctrans('Issue')" />
			</div>

			<label class="block space-y-1">
				<span class="text-xs text-gray-500">{{ ctrans("Note for the supplier") }} <span v-if="needsComment" class="text-red-500">*</span></span>
				<Textarea v-model="form.comment" rows="3" autoResize fluid :disabled="isReadonly" :placeholder="ctrans('e.g. 3 of 12 jars arrived broken, the rest fine')" />
			</label>

			<div v-if="!existing" class="space-y-1">
				<span class="text-xs text-gray-500">{{ ctrans("Photos (optional, up to :max)", { max: options.max_photos ?? 10 }) }}</span>
				<FileUpload mode="basic" multiple accept="image/*,application/pdf" :auto="false" :fileLimit="options.max_photos ?? 10" :chooseLabel="ctrans('Add photos')" @select="(event) => form.photos = event.files" />
			</div>

			<p class="text-xs text-gray-500">
				{{ options.sent ? ctrans("The delivery feedback was already sent: the supplier's buyer is told about this note now.") : ctrans("It is sent with the delivery feedback, when you press Give delivery feedback.") }}
			</p>

			<div v-for="(error, field) in form.errors" :key="field" class="text-xs text-red-600">{{ error }}</div>
			<div v-if="deleteError" class="text-xs text-red-600">{{ deleteError }}</div>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button v-if="existing?.is_mine && !options.sent" type="tertiary" class="shrink-0" :label="ctrans('Remove')" :loading="deleting" @click="remove">
					<template #icon><FontAwesomeIcon :icon="faTrashAlt" class="text-amber-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button v-if="!isReadonly" type="primary" full :label="existing ? ctrans('Save') : ctrans('Save item feedback')" :loading="form.processing" :disabled="!canSubmit" @click="submit">
					<template #icon><FontAwesomeIcon :icon="faPaperPlane" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>

<style scoped>
.feedback-issues {
	display: flex;
	flex-wrap: wrap;
	gap: 0.375rem;
}
.feedback-issues :deep(.p-togglebutton) {
	border: 1px solid rgb(209 213 219);
	border-radius: 9999px !important;
	background: #fff;
	color: rgb(75 85 99);
	font-size: 0.8125rem;
	padding: 0;
}
.feedback-issues :deep(.p-togglebutton-content) {
	padding: 0.25rem 0.75rem;
	border-radius: 9999px;
	background: transparent;
	box-shadow: none;
}
.feedback-issues :deep(.p-togglebutton-checked) {
	border-color: var(--app-accent-muted);
	background: var(--app-accent-soft);
	color: var(--app-accent-deep);
	font-weight: 600;
}
</style>
