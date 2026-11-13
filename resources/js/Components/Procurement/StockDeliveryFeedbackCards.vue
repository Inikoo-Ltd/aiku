<script setup lang="ts">
import { computed, ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Rating from "primevue/rating"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import StockDeliveryFeedbackDialog, { type FeedbackOptions } from "@/Components/Procurement/StockDeliveryFeedbackDialog.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faExclamationTriangle, faPaperclip, faPencil, faStar, faThumbtack, faTimes } from "@fal"

export interface FeedbackCard {
	id: number
	rating: number
	rating_description: string | null
	topics: { value: string; label: string }[]
	comment: string | null
	stock_delivery: { reference: string; date: string | null }
	organisation: string
	supplier: { id: number; code: string; name: string } | null
	agent: { code: string; name: string } | null
	purchase_orders: string[]
	author: string | null
	created_at: string
	photos?: { media_ulid: string; caption: string | null; file_type: string | null; download_route: routeType }[]
	instruction: { id: number; body: string; state: string } | null
	open_discrepancies?: { id: number; code: string | null; expected: number; received: number; outcome: string | null; outcome_label: string | null; claim_state: string | null }[]
	make_instruction_route?: routeType | null
	update_route?: routeType | null
}

const props = defineProps<{
	data?: { cards: FeedbackCard[] }
	cards?: FeedbackCard[]
	options?: FeedbackOptions | null
	portal?: boolean
}>()

defineOptions({ inheritAttrs: false })

const locale = useLocaleStore()
const list = computed(() => props.cards ?? props.data?.cards ?? [])
const isImage = (fileType: string | null) => ["jpg", "jpeg", "png", "webp"].includes(fileType ?? "")

const editing = ref<FeedbackCard | null>(null)
const instructionFor = ref<FeedbackCard | null>(null)
const instructionForm = useForm({ title: "", body: "" })

const openInstruction = (card: FeedbackCard) => {
	instructionForm.clearErrors()
	instructionForm.title = ""
	instructionForm.body = card.comment ?? ""
	instructionFor.value = card
}

const submitInstruction = () => {
	const target = instructionFor.value?.make_instruction_route
	if (!target || !instructionForm.body.trim()) {
		return
	}
	instructionForm.post(route(target.name, target.parameters), {
		preserveScroll: true,
		onSuccess: () => (instructionFor.value = null),
	})
}
</script>

<template>
	<div :class="portal ? 'space-y-3' : 'space-y-3 p-4'">
		<div v-if="!list.length" class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-sm text-gray-500">
			<FontAwesomeIcon :icon="faStar" class="mb-2 text-2xl text-gray-300" aria-hidden="true" />
			<p class="font-medium text-gray-700">{{ ctrans("No delivery feedback yet") }}</p>
			<p>{{ portal ? ctrans("When we receive one of your deliveries, our warehouse tells you here how it arrived.") : ctrans("Use \"Give delivery feedback\" to tell the supplier how this delivery arrived.") }}</p>
		</div>

		<article v-for="card in list" :key="card.id" class="rounded-lg border border-gray-200 bg-white p-4 text-sm shadow-sm">
			<div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
				<div class="min-w-0">
					<div class="flex flex-wrap items-center gap-x-2 gap-y-1">
						<Rating :modelValue="card.rating" readonly />
						<span class="font-medium text-gray-800">{{ card.rating_description }}</span>
					</div>
					<div class="mt-1 text-xs text-gray-500">
						<span class="whitespace-nowrap font-medium text-gray-700">{{ card.stock_delivery.reference }}</span>
						<template v-if="card.stock_delivery.date"> · {{ useFormatTime(card.stock_delivery.date) }}</template>
						<template v-if="portal"> · {{ card.organisation }}</template>
						<template v-else-if="card.supplier"> · <span class="whitespace-nowrap">{{ card.supplier.code }}</span> {{ card.supplier.name }}</template>
						<template v-if="!portal && card.agent"> · {{ ctrans("Agent") }} <span class="whitespace-nowrap">{{ card.agent.code }}</span></template>
					</div>
					<div v-if="card.purchase_orders.length" class="mt-0.5 text-xs text-gray-500">
						{{ ctrans("Purchase orders") }}:
						<span v-for="(reference, index) in card.purchase_orders" :key="reference" class="whitespace-nowrap">{{ reference }}<template v-if="index < card.purchase_orders.length - 1">, </template></span>
					</div>
				</div>
				<div v-if="card.update_route || card.make_instruction_route" class="flex shrink-0 flex-wrap gap-2">
					<Button v-if="card.update_route && options" type="tertiary" size="xs" :label="ctrans('Edit')" @click="editing = card">
						<template #icon><FontAwesomeIcon :icon="faPencil" class="text-gray-500" fixed-width aria-hidden="true" /></template>
					</Button>
					<Button v-if="card.make_instruction_route" type="tertiary" size="xs" :label="ctrans('Make supplier instruction')" @click="openInstruction(card)">
						<template #icon><FontAwesomeIcon :icon="faThumbtack" class="text-green-600" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</div>

			<div v-if="card.topics.length" class="mt-3 flex flex-wrap gap-1.5">
				<span v-for="topic in card.topics" :key="topic.value" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ topic.label }}</span>
			</div>

			<p v-if="card.comment" class="mt-3 whitespace-pre-line text-gray-800">{{ card.comment }}</p>

			<div v-if="card.photos?.length" class="mt-3 flex flex-wrap gap-2">
				<a v-for="photo in card.photos" :key="photo.media_ulid" :href="route(photo.download_route.name, photo.download_route.parameters)" target="_blank" rel="noopener" class="flex h-16 w-16 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-50 text-gray-500 hover:border-gray-400" :title="photo.caption ?? ctrans('Photo')">
					<img v-if="isImage(photo.file_type)" :src="route(photo.download_route.name, photo.download_route.parameters)" :alt="photo.caption ?? ctrans('Photo')" loading="lazy" class="h-full w-full object-cover" />
					<span v-else class="flex flex-col items-center text-xxs uppercase">
						<FontAwesomeIcon :icon="faPaperclip" class="text-base" aria-hidden="true" />
						{{ photo.file_type }}
					</span>
				</a>
			</div>

			<div v-if="card.instruction" class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3">
				<div class="flex flex-wrap items-center gap-2 text-xs">
					<span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800">
						<FontAwesomeIcon :icon="faThumbtack" fixed-width aria-hidden="true" />
						{{ ctrans("Supplier instruction") }}
					</span>
					<span v-if="!portal" class="text-gray-500">{{ card.instruction.state.replaceAll("_", " ") }}</span>
				</div>
				<p class="mt-1.5 whitespace-pre-line text-gray-700">{{ card.instruction.body }}</p>
			</div>

			<div v-if="card.open_discrepancies?.length" class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-900">
				<div class="flex items-center gap-1.5 text-xs font-semibold">
					<FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
					{{ ctrans("Open discrepancies on this delivery: :count", { count: locale.number(card.open_discrepancies.length) }) }}
				</div>
				<ul class="mt-1.5 space-y-1 text-xs">
					<li v-for="line in card.open_discrepancies" :key="line.id" class="flex flex-wrap items-baseline gap-x-3">
						<span class="whitespace-nowrap font-medium">{{ line.code }}</span>
						<span class="tabular-nums">{{ ctrans("expected :expected, received :received", { expected: locale.number(line.expected), received: locale.number(line.received) }) }}</span>
						<span>{{ line.outcome_label ?? ctrans("Needs decision") }}<template v-if="line.claim_state"> · {{ ctrans("claim :state", { state: line.claim_state.replaceAll("_", " ") }) }}</template></span>
					</li>
				</ul>
			</div>

			<div class="mt-3 text-xs text-gray-400">
				<template v-if="card.author && !portal">{{ card.author }} · </template>{{ useFormatTime(card.created_at) }}
			</div>
		</article>

		<StockDeliveryFeedbackDialog v-if="options" :visible="!!editing" :options="options" :feedback="editing" @update:visible="(isVisible) => !isVisible && (editing = null)" />

		<Dialog :visible="!!instructionFor" modal :header="ctrans('Make supplier instruction')" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false" @update:visible="(isVisible) => !isVisible && (instructionFor = null)">
			<div class="space-y-3 text-sm">
				<p class="text-xs text-gray-500">{{ ctrans("A pinned note on :supplier: it shows on its next purchase orders and on the goods-in checklist.", { supplier: instructionFor?.supplier?.name ?? "" }) }}</p>
				<label class="block space-y-1">
					<span class="text-xs text-gray-500">{{ ctrans("Title (optional)") }}</span>
					<InputText v-model="instructionForm.title" fluid />
				</label>
				<label class="block space-y-1">
					<span class="text-xs text-gray-500">{{ ctrans("Instruction") }} <span class="text-red-500">*</span></span>
					<Textarea v-model="instructionForm.body" rows="4" autoResize fluid />
				</label>
				<div v-for="(error, field) in instructionForm.errors" :key="field" class="text-xs text-red-600">{{ error }}</div>
			</div>
			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="instructionFor = null">
						<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
					</Button>
					<Button type="primary" full :label="ctrans('Make supplier instruction')" :loading="instructionForm.processing" :disabled="!instructionForm.body.trim()" @click="submitInstruction">
						<template #icon><FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</template>
		</Dialog>
	</div>
</template>
