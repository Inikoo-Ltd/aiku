<script setup lang="ts">
import { computed, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import FileUpload from "primevue/fileupload"
import Rating from "primevue/rating"
import Select from "primevue/select"
import SelectButton from "primevue/selectbutton"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperPlane, faTimes } from "@fal"

export interface FeedbackOptions {
	suppliers?: { id: number | null; code: string | null; name: string | null; purchase_orders: string[] }[]
	rating_descriptions: { value: number; label: string }[]
	topics: { value: string; label: string }[]
	default_comments: Record<number, string>
	placeholders: Record<number, string>
	max_photos?: number
	store_route?: routeType
}

export interface EditableFeedback {
	rating: number
	topics: { value: string; label: string }[]
	comment: string | null
	update_route?: routeType | null
}

const props = defineProps<{
	options: FeedbackOptions
	feedback?: EditableFeedback | null
}>()

const emit = defineEmits<{
	(e: "saved"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const form = useForm<{
	supplier_id: number | null
	rating: number | null
	topics: string[]
	comment: string
	photos: File[]
}>({
	supplier_id: null,
	rating: null,
	topics: [],
	comment: "",
	photos: [],
})

const suppliers = computed(() => props.options.suppliers ?? [])
const supplier = computed(() => suppliers.value.find((entry) => entry.id === form.supplier_id) ?? (suppliers.value.length === 1 ? suppliers.value[0] : null))
const isEditing = computed(() => !!props.feedback)
const needsSupplier = computed(() => !isEditing.value && suppliers.value.length > 1)
const needsComment = computed(() => form.rating !== null && form.rating < 5)
const ratingDescription = computed(() => props.options.rating_descriptions.find((description) => description.value === form.rating)?.label)
const canSubmit = computed(() => form.rating !== null && (!needsComment.value || !!form.comment.trim()) && (!needsSupplier.value || form.supplier_id !== null))

watch(visible, (isVisible) => {
	if (!isVisible) {
		return
	}
	form.clearErrors()
	form.supplier_id = suppliers.value.length === 1 ? suppliers.value[0].id : null
	form.rating = props.feedback?.rating ?? null
	form.topics = props.feedback?.topics.map((topic) => topic.value) ?? []
	form.comment = props.feedback?.comment ?? ""
	form.photos = []
})

watch(() => form.rating, (rating) => {
	const defaults = Object.values(props.options.default_comments)
	if (rating !== null && props.options.default_comments[rating] && !form.comment.trim()) {
		form.comment = props.options.default_comments[rating]
	} else if (rating !== null && !props.options.default_comments[rating] && defaults.includes(form.comment)) {
		form.comment = ""
	}
})

const submit = () => {
	const target = isEditing.value ? props.feedback?.update_route : props.options.store_route
	if (!target || !canSubmit.value) {
		return
	}

	form
		.transform((data) => (isEditing.value ? { rating: data.rating, topics: data.topics, comment: data.comment } : data))
		.submit(isEditing.value ? "patch" : "post", route(target.name, target.parameters), {
			preserveScroll: true,
			forceFormData: !isEditing.value,
			onSuccess: () => {
				visible.value = false
				emit("saved")
			},
		})
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="isEditing ? ctrans('Edit delivery feedback') : ctrans('Give delivery feedback')" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div class="space-y-4 text-sm">
			<label v-if="needsSupplier" class="block space-y-1">
				<span class="text-xs text-gray-500">{{ ctrans("Supplier") }} <span class="text-red-500">*</span></span>
				<Select v-model="form.supplier_id" :options="suppliers" optionValue="id" :optionLabel="(entry) => `${entry.code} · ${entry.name}`" :placeholder="ctrans('Choose the supplier this feedback is about')" fluid />
			</label>
			<div v-if="!isEditing && supplier" class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
				<span class="whitespace-nowrap font-medium text-gray-800">{{ supplier.code }}</span>
				<span class="ml-1">{{ supplier.name }}</span>
				<div v-if="supplier.purchase_orders.length" class="mt-0.5">
					{{ ctrans("Purchase orders") }}:
					<span v-for="(reference, index) in supplier.purchase_orders" :key="reference" class="whitespace-nowrap">{{ reference }}<template v-if="index < supplier.purchase_orders.length - 1">, </template></span>
				</div>
			</div>

			<div class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("How did it arrive?") }} <span class="text-red-500">*</span></span>
				<Rating v-model="form.rating" class="feedback-rating" />
				<p class="min-h-5 text-sm" :class="form.rating ? 'font-medium text-gray-800' : 'text-gray-400'">{{ ratingDescription ?? ctrans("Choose from 1 to 5 stars") }}</p>
			</div>

			<div class="space-y-1">
				<span class="text-xs text-gray-500">{{ ctrans("What is it about? (optional)") }}</span>
				<SelectButton v-model="form.topics" :options="options.topics" optionLabel="label" optionValue="value" multiple class="feedback-topics" :aria-label="ctrans('Topics')" />
			</div>

			<label class="block space-y-1">
				<span class="text-xs text-gray-500">{{ ctrans("Comment for the supplier") }} <span v-if="needsComment" class="text-red-500">*</span></span>
				<Textarea v-model="form.comment" rows="3" autoResize fluid :placeholder="form.rating ? options.placeholders[form.rating] : undefined" />
			</label>

			<div v-if="!isEditing" class="space-y-1">
				<span class="text-xs text-gray-500">{{ ctrans("Photos (optional, up to :max)", { max: options.max_photos ?? 10 }) }}</span>
				<FileUpload mode="basic" multiple accept="image/*,application/pdf" :auto="false" :fileLimit="options.max_photos ?? 10" :chooseLabel="ctrans('Add photos')" @select="(event) => form.photos = event.files" />
			</div>

			<div v-for="(error, field) in form.errors" :key="field" class="text-xs text-red-600">{{ error }}</div>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="primary" full :label="isEditing ? ctrans('Save feedback') : ctrans('Send feedback')" :loading="form.processing" :disabled="!canSubmit" @click="submit">
					<template #icon><FontAwesomeIcon :icon="faPaperPlane" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>

<style scoped>
.feedback-rating {
	gap: 0.5rem;
}
.feedback-rating :deep(.p-rating-icon) {
	width: 1.75rem;
	height: 1.75rem;
}
.feedback-topics {
	display: flex;
	flex-wrap: wrap;
	gap: 0.375rem;
}
.feedback-topics :deep(.p-togglebutton) {
	border: 1px solid rgb(209 213 219);
	border-radius: 9999px !important;
	background: #fff;
	color: rgb(75 85 99);
	font-size: 0.8125rem;
	padding: 0;
}
.feedback-topics :deep(.p-togglebutton-content) {
	padding: 0.25rem 0.75rem;
	border-radius: 9999px;
	background: transparent;
	box-shadow: none;
}
.feedback-topics :deep(.p-togglebutton-checked) {
	border-color: var(--app-accent-muted);
	background: var(--app-accent-soft);
	color: var(--app-accent-deep);
	font-weight: 600;
}
</style>
