<script setup lang="ts">
import { computed, ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Rating from "primevue/rating"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import StockDeliveryFeedbackDialog, { type FeedbackOptions } from "@/Components/Procurement/StockDeliveryFeedbackDialog.vue"
import StockDeliveryItemFeedbackDialog, { type ItemFeedbackLine } from "@/Components/Procurement/StockDeliveryItemFeedbackDialog.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faExclamationTriangle, faPaperclip, faPencil, faStar, faThumbtack, faTimes, faCommentAltExclamation, faHourglassHalf } from "@fal"

type Photo = { media_ulid: string; caption: string | null; file_type: string | null; url: string }

export interface ItemNote {
	id: number
	stock_delivery_item_id: number
	code: string | null
	org_stock_code: string | null
	name: string | null
	supplier: { id: number; code: string; name: string } | null
	issue: string
	issue_label: string
	comment: string | null
	author: string | null
	author_id: number | null
	created_at: string
	photos: Photo[]
	instruction: { id: number; body: string; state: string | null } | null
	make_instruction_route?: routeType | null
	update_route?: routeType | null
	delete_route?: routeType | null
}

export interface FeedbackCard {
	id: number
	sent_at: string | null
	rating: number | null
	rating_description: string | null
	topics: { value: string; label: string }[]
	comment: string | null
	stock_delivery: { reference: string; date: string | null }
	organisation: string
	recipient?: { code: string | null; name: string | null; is_agent: boolean }
	supplier: { id: number; code: string; name: string } | null
	agent: { code: string; name: string } | null
	purchase_orders: string[]
	author: string | null
	created_at: string
	photos?: Photo[]
	instruction: { id: number; body: string; state: string | null } | null
	items: ItemNote[]
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

const emit = defineEmits<{ giveFeedback: [] }>()

defineOptions({ inheritAttrs: false })

const locale = useLocaleStore()
const list = computed(() => props.cards ?? props.data?.cards ?? [])
const isImage = (fileType: string | null) => ["jpg", "jpeg", "png", "webp"].includes(fileType ?? "")

const editing = ref<FeedbackCard | null>(null)
const editingNote = ref<ItemFeedbackLine | null>(null)
const isNoteOpen = ref(false)
const instructionFor = ref<{ route: routeType; supplier: string } | null>(null)
const instructionForm = useForm({ title: "", body: "" })

const openInstruction = (target: routeType, body: string | null, supplier: string) => {
	instructionForm.clearErrors()
	instructionForm.title = ""
	instructionForm.body = body ?? ""
	instructionFor.value = { route: target, supplier }
}

const noteBody = (note: ItemNote) => [note.code, note.issue_label, note.comment].filter(Boolean).join(" · ")

const openNote = (note: ItemNote) => {
	editingNote.value = {
		id: note.stock_delivery_item_id,
		code: note.code,
		description: note.name,
		supplier_code: note.supplier?.code,
		feedback: { id: note.id, issue: note.issue, issue_label: note.issue_label, comment: note.comment, is_mine: !!note.update_route },
	}
	isNoteOpen.value = true
}

const submitInstruction = () => {
	const target = instructionFor.value?.route
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
			<Button
				v-if="!portal && options && !options.sent"
				type="secondary"
				icon="fal fa-star"
				class="mt-4"
				:label="ctrans('Give delivery feedback')"
				@click="emit('giveFeedback')" />
		</div>

		<article v-for="card in list" :key="card.id" class="rounded-lg border border-gray-200 bg-white p-4 text-sm shadow-sm">
			<div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
				<div class="min-w-0">
					<div v-if="card.rating" class="flex flex-wrap items-center gap-x-2 gap-y-1">
						<Rating :modelValue="card.rating" readonly />
						<span class="font-medium text-gray-800">{{ card.rating_description }}</span>
					</div>
					<div v-else-if="!card.sent_at" class="flex items-center gap-1.5 font-medium text-amber-700">
						<FontAwesomeIcon :icon="faHourglassHalf" fixed-width aria-hidden="true" />
						{{ ctrans("Not sent yet") }}
					</div>
					<div class="mt-1 text-xs text-gray-500">
						<span class="whitespace-nowrap font-medium text-gray-700">{{ card.stock_delivery.reference }}</span>
						<template v-if="card.stock_delivery.date"> · {{ useFormatTime(card.stock_delivery.date) }}</template>
						<template v-if="portal"> · {{ card.organisation }}</template>
						<template v-else-if="card.recipient"> · {{ card.recipient.is_agent ? ctrans("Agent") : ctrans("Supplier") }} <span class="whitespace-nowrap">{{ card.recipient.code }}</span> {{ card.recipient.name }}</template>
					</div>
					<div v-if="card.purchase_orders.length" class="mt-0.5 text-xs text-gray-500">
						{{ ctrans("Purchase orders") }}:
						<template v-for="(reference, index) in card.purchase_orders" :key="reference"><span class="whitespace-nowrap">{{ reference }}</span><template v-if="index < card.purchase_orders.length - 1">, </template></template>
					</div>
				</div>
				<div v-if="card.update_route || card.make_instruction_route" class="flex shrink-0 flex-wrap gap-2">
					<Button v-if="card.update_route && options" type="tertiary" size="xs" :label="ctrans('Edit')" @click="editing = card">
						<template #icon><FontAwesomeIcon :icon="faPencil" class="text-gray-500" fixed-width aria-hidden="true" /></template>
					</Button>
					<Button v-if="card.make_instruction_route" type="tertiary" size="xs" :label="ctrans('Make supplier instruction')" @click="openInstruction(card.make_instruction_route, card.comment, card.supplier?.name ?? '')">
						<template #icon><FontAwesomeIcon :icon="faThumbtack" class="text-green-600" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</div>

			<div v-if="!card.sent_at && !portal" class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
				<span>{{ ctrans("The item notes below wait for the delivery feedback: give it to send them all to their suppliers.") }}</span>
				<Button v-if="options && !options.sent" type="tertiary" size="xs" :label="ctrans('Give delivery feedback')" @click="emit('giveFeedback')">
					<template #icon><FontAwesomeIcon :icon="faStar" class="text-amber-500" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>

			<div v-if="card.topics.length" class="mt-3 flex flex-wrap gap-1.5">
				<span v-for="topic in card.topics" :key="topic.value" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ topic.label }}</span>
			</div>

			<p v-if="card.comment" class="mt-3 whitespace-pre-line text-gray-800">{{ card.comment }}</p>

			<div v-if="card.photos?.length" class="mt-3 flex flex-wrap gap-2">
				<a v-for="photo in card.photos" :key="photo.media_ulid" :href="photo.url" target="_blank" rel="noopener" class="flex h-16 w-16 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-50 text-gray-500 hover:border-gray-400" :title="photo.caption ?? ctrans('Photo')">
					<img v-if="isImage(photo.file_type)" :src="photo.url" :alt="photo.caption ?? ctrans('Photo')" loading="lazy" class="h-full w-full object-cover" />
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

			<div v-if="card.items.length" class="mt-3 rounded-md border border-gray-200">
				<div class="flex items-center gap-1.5 border-b border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700">
					<FontAwesomeIcon :icon="faCommentAltExclamation" class="text-amber-500" fixed-width aria-hidden="true" />
					{{ portal ? ctrans("Your items") : ctrans("Item notes: :count", { count: locale.number(card.items.length) }) }}
				</div>
				<ul class="divide-y divide-gray-100">
					<li v-for="note in card.items" :key="note.id" class="px-3 py-2">
						<div class="flex flex-wrap items-start justify-between gap-2">
							<div class="min-w-0 text-xs">
								<span class="whitespace-nowrap font-medium text-gray-800">{{ note.code }}</span>
								<span v-if="note.name" class="ml-1 text-gray-600">{{ note.name }}</span>
								<template v-if="!portal && note.supplier"> · <span class="whitespace-nowrap text-gray-500">{{ note.supplier.code }}</span></template>
								<div class="mt-1 flex flex-wrap items-center gap-2">
									<span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800">{{ note.issue_label }}</span>
									<span v-if="note.author && !portal" class="text-gray-400">{{ note.author }}</span>
								</div>
							</div>
							<div v-if="note.update_route || note.make_instruction_route" class="flex shrink-0 flex-wrap gap-2">
								<Button v-if="note.update_route && options" type="tertiary" size="xs" :label="ctrans('Edit')" @click="openNote(note)">
									<template #icon><FontAwesomeIcon :icon="faPencil" class="text-gray-500" fixed-width aria-hidden="true" /></template>
								</Button>
								<Button v-if="note.make_instruction_route" type="tertiary" size="xs" :label="ctrans('Make supplier instruction')" @click="openInstruction(note.make_instruction_route, noteBody(note), note.supplier?.name ?? '')">
									<template #icon><FontAwesomeIcon :icon="faThumbtack" class="text-green-600" fixed-width aria-hidden="true" /></template>
								</Button>
							</div>
						</div>
						<p v-if="note.comment" class="mt-1 whitespace-pre-line text-gray-800">{{ note.comment }}</p>
						<div v-if="note.photos.length" class="mt-2 flex flex-wrap gap-2">
							<a v-for="photo in note.photos" :key="photo.media_ulid" :href="photo.url" target="_blank" rel="noopener" class="flex h-12 w-12 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-50 text-gray-500 hover:border-gray-400" :title="photo.caption ?? ctrans('Photo')">
								<img v-if="isImage(photo.file_type)" :src="photo.url" :alt="photo.caption ?? ctrans('Photo')" loading="lazy" class="h-full w-full object-cover" />
								<FontAwesomeIcon v-else :icon="faPaperclip" aria-hidden="true" />
							</a>
						</div>
						<div v-if="note.instruction" class="mt-2 flex items-start gap-1.5 text-xs text-green-800">
							<FontAwesomeIcon :icon="faThumbtack" class="mt-0.5" fixed-width aria-hidden="true" />
							<span class="whitespace-pre-line">{{ note.instruction.body }}</span>
						</div>
					</li>
				</ul>
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

		<StockDeliveryFeedbackDialog v-if="options" :visible="!!editing" :options="options" :feedback="editing?.rating ? { rating: editing.rating, topics: editing.topics, comment: editing.comment, update_route: editing.update_route } : null" @update:visible="(isVisible) => !isVisible && (editing = null)" />
		<StockDeliveryItemFeedbackDialog v-if="options" v-model:visible="isNoteOpen" :options="options" :line="editingNote" />

		<Dialog :visible="!!instructionFor" modal :header="ctrans('Make supplier instruction')" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false" @update:visible="(isVisible) => !isVisible && (instructionFor = null)">
			<div class="space-y-3 text-sm">
				<p class="text-xs text-gray-500">{{ ctrans("A pinned note on :supplier: it shows on its next purchase orders and on the goods-in checklist.", { supplier: instructionFor?.supplier ?? "" }) }}</p>
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
