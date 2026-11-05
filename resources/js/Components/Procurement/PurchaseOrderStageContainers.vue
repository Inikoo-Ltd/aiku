<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-05 08:37
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, nextTick, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import Dialog from "primevue/dialog"
import Popover from "primevue/popover"
import Image from "@common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import PurchaseOrderLineAddToContainerDialog from "@/Components/Procurement/PurchaseOrderLineAddToContainerDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxes, faPlus, faTimes, faTrashAlt, faExclamationTriangle, faInfoCircle, faPaperclip, faFilePdf, faFileImage, faFileAlt } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import { Image as ImageType } from "@/types/Image"

type ItemToRemove = {
	id: number
	code: string | null
	name: string | null
	image_thumbnail: ImageType | null
	unit_quantity: number
}

type Attachment = {
	id: number
	caption: string | null
	scope: string | null
	scope_label: string | null
	file_name: string | null
	file_type: string | null
	size: number | null
	url: string
}

type Container = {
	id: number
	reference: string
	state: string
	state_label: string
	state_icon: { icon: string; class: string } | null
	number_lines: number
	unit_quantity: number
	number_attachments: number
	attachments_route: routeType
	show_route: routeType
	items_to_remove: ItemToRemove[]
	remove_route: routeType | null
}

const props = defineProps<{
	containers: Container[]
	itemsToAdd: InstanceType<typeof PurchaseOrderLineAddToContainerDialog>["$props"]["items"]
}>()

const locale = useLocaleStore()

const isDialogVisible = ref(false)

const unitsToAdd = computed(() => props.itemsToAdd.reduce((total, item) => total + item.agent_warehouse.quantity_ready_to_ship, 0))

const attachmentsPopover = ref<InstanceType<typeof Popover> | null>(null)
const attachmentsContainer = ref<Container | null>(null)
const attachments = ref<Attachment[]>([])
const isFetchingAttachments = ref(false)
const attachmentsError = ref<string | null>(null)
const isAttachmentsOpen = ref(false)
let attachmentsRequest = 0

async function showAttachments(event: MouseEvent, container: Container) {
	const target = event.currentTarget as HTMLElement
	if (isAttachmentsOpen.value && attachmentsContainer.value?.id === container.id) {
		attachmentsPopover.value?.hide()
		return
	}

	const request = ++attachmentsRequest
	attachmentsContainer.value = container
	attachments.value = []
	attachmentsError.value = null
	isFetchingAttachments.value = true

	attachmentsPopover.value?.hide()
	await nextTick()
	attachmentsPopover.value?.show(event, target)

	try {
		const response = await axios.get(route(container.attachments_route.name, container.attachments_route.parameters))
		if (request === attachmentsRequest) {
			attachments.value = response.data.data
		}
	} catch (error: any) {
		if (request === attachmentsRequest) {
			attachmentsError.value = error?.response?.data?.message || ctrans("Something went wrong")
		}
	} finally {
		if (request === attachmentsRequest) {
			isFetchingAttachments.value = false
		}
	}
}

const attachmentIcon = (attachment: Attachment) => {
	if (attachment.file_type === "pdf") {
		return faFilePdf
	}

	return ["jpg", "jpeg", "png", "gif", "webp", "heic"].includes(attachment.file_type ?? "") ? faFileImage : faFileAlt
}

const formatFileSize = (bytes: number | null) => {
	if (bytes === null) {
		return null
	}
	if (bytes < 1024 * 1024) {
		return `${locale.number(Math.max(1, Math.round(bytes / 1024)))} KB`
	}

	return `${locale.number(Math.round((bytes / (1024 * 1024)) * 10) / 10)} MB`
}

const containerToRemove = ref<Container | null>(null)
const removeUnits = computed(() => (containerToRemove.value?.items_to_remove ?? []).reduce((total, item) => total + item.unit_quantity, 0))
const isRemoving = ref(false)
const removeError = ref<string | null>(null)

function askRemove(container: Container) {
	removeError.value = null
	containerToRemove.value = container
}

function removeFromContainer() {
	const removeRoute = containerToRemove.value?.remove_route
	if (!removeRoute) {
		return
	}

	router.delete(route(removeRoute.name, removeRoute.parameters), {
		preserveScroll: true,
		onStart: () => {
			isRemoving.value = true
			removeError.value = null
		},
		onFinish: () => (isRemoving.value = false),
		onSuccess: () => (containerToRemove.value = null),
		onError: (errors: Record<string, string>) => (removeError.value = Object.values(errors ?? {})[0] || ctrans("Something went wrong")),
	})
}
</script>

<template>
	<div class="mt-2 space-y-2 text-xs">
		<ul v-if="containers.length" class="space-y-1">
			<li v-for="container in containers" :key="container.id" class="flex flex-wrap items-baseline justify-between gap-x-2">
				<div class="flex min-w-0 items-center gap-1">
					<FontAwesomeIcon
						v-if="container.state_icon"
						:icon="container.state_icon.icon"
						:class="container.state_icon.class"
						v-tooltip="container.state_label"
						fixed-width
						aria-hidden="true"
					/>
					<Link :href="route(container.show_route.name, container.show_route.parameters)" class="whitespace-nowrap font-medium text-[var(--app-accent)] hover:underline">
						{{ container.reference }}
					</Link>
					<button
						v-if="container.number_attachments"
						type="button"
						class="inline-flex shrink-0 items-center gap-0.5 rounded px-0.5 tabular-nums text-gray-500 hover:bg-[var(--app-accent-soft)] hover:text-[var(--app-accent)]"
						v-tooltip="ctrans('Click to see the files attached to :container', { container: container.reference })"
						:aria-label="ctrans(':count files attached to :container', { count: String(container.number_attachments), container: container.reference })"
						@click="(event: MouseEvent) => showAttachments(event, container)"
					>
						<FontAwesomeIcon :icon="faPaperclip" fixed-width aria-hidden="true" />
						{{ container.number_attachments }}
					</button>
					<span
						v-else
						class="inline-flex shrink-0 items-center gap-0.5 px-0.5 tabular-nums text-gray-300"
						v-tooltip="ctrans('No file attached to :container yet', { container: container.reference })"
					>
						<FontAwesomeIcon :icon="faPaperclip" fixed-width aria-hidden="true" />
						0
					</span>
				</div>
				<span class="ml-auto flex items-baseline gap-1">
					<span class="flex flex-wrap justify-end gap-x-1 text-right tabular-nums text-gray-500">
						<span class="whitespace-nowrap">{{ ctrans(":count items", { count: String(container.number_lines) }) }},</span>
						<span class="whitespace-nowrap">{{ ctrans(":units units", { units: locale.number(container.unit_quantity) }) }}</span>
					</span>
					<button
						v-if="container.remove_route"
						type="button"
						class="shrink-0 rounded px-0.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
						v-tooltip="ctrans('Take this order out of :container', { container: container.reference })"
						:aria-label="ctrans('Take this order out of :container', { container: container.reference })"
						@click="askRemove(container)"
					>
						<FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
					</button>
				</span>
			</li>
		</ul>
		<p v-else class="text-gray-400">{{ ctrans("No item in a container yet") }}</p>

		<Button
			v-if="itemsToAdd.length"
			type="tertiary"
			size="xs"
			full
			:label="ctrans('Add all to a container (:count)', { count: String(itemsToAdd.length) })"
			:tooltip="ctrans(':count items not in a container yet, :units units: check them before they go in', { count: String(itemsToAdd.length), units: locale.number(unitsToAdd) })"
			@click="isDialogVisible = true"
		>
			<template #icon>
				<FontAwesomeIcon :icon="containers.length ? faPlus : faBoxes" class="text-green-600" fixed-width aria-hidden="true" />
			</template>
		</Button>

		<PurchaseOrderLineAddToContainerDialog v-if="itemsToAdd.length" v-model:visible="isDialogVisible" :items="itemsToAdd" />

		<Popover ref="attachmentsPopover" @show="isAttachmentsOpen = true" @hide="isAttachmentsOpen = false">
			<div class="w-72 max-w-[calc(100vw-2rem)] space-y-2 text-xs">
				<div class="font-semibold text-gray-700">
					{{ ctrans("Files of :container", { container: attachmentsContainer?.reference ?? "" }) }}
				</div>
				<div v-if="isFetchingAttachments" class="flex justify-center py-3">
					<LoadingIcon />
				</div>
				<p v-else-if="attachmentsError" class="text-red-600">{{ attachmentsError }}</p>
				<p v-else-if="!attachments.length" class="text-gray-400">{{ ctrans("No file attached yet") }}</p>
				<ul v-else class="max-h-72 divide-y divide-gray-100 overflow-y-auto">
					<li v-for="attachment in attachments" :key="attachment.id">
						<a :href="attachment.url" target="_blank" rel="noopener" class="flex items-start gap-2 rounded px-1 py-1.5 hover:bg-gray-50">
							<FontAwesomeIcon :icon="attachmentIcon(attachment)" class="mt-0.5 text-gray-500" fixed-width aria-hidden="true" />
							<span class="min-w-0 flex-1">
								<span class="block break-words text-[var(--app-accent)]">{{ attachment.caption || attachment.file_name }}</span>
								<span class="block text-gray-500">
									{{ [attachment.scope_label, formatFileSize(attachment.size)].filter(Boolean).join(" · ") }}
								</span>
							</span>
						</a>
					</li>
				</ul>
			</div>
		</Popover>

		<Dialog
			:visible="!!containerToRemove"
			modal
			:header="ctrans('Take this order out of :container', { container: containerToRemove?.reference ?? '' })"
			:style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }"
			:draggable="false"
			@update:visible="(visible: boolean) => !visible && (containerToRemove = null)"
		>
			<div v-if="containerToRemove" class="space-y-3 text-sm">
				<p class="text-gray-600">
					{{ ctrans("These items of this order come out of :container and wait for a container again:", { container: containerToRemove.reference }) }}
				</p>

				<ul class="max-h-[50vh] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200">
					<li v-for="item in containerToRemove.items_to_remove" :key="item.id" class="flex items-center gap-3 px-3 py-2">
						<Image :src="item.image_thumbnail" imageCover class="h-10 w-10 shrink-0 overflow-hidden rounded border border-gray-200" />
						<div class="min-w-0 flex-1">
							<div class="whitespace-nowrap font-medium text-gray-800">{{ item.code }}</div>
							<div v-if="item.name" class="text-xs text-gray-500">{{ item.name }}</div>
						</div>
						<span class="shrink-0 whitespace-nowrap tabular-nums text-gray-700">{{ ctrans(":units units", { units: locale.number(item.unit_quantity) }) }}</span>
					</li>
				</ul>

				<p class="text-right text-xs tabular-nums text-gray-600">
					{{ ctrans("Total: :count items, :units units", { count: String(containerToRemove.items_to_remove.length), units: locale.number(removeUnits) }) }}
				</p>

				<p class="flex gap-2 rounded-md border border-gray-200 bg-gray-50 p-2 text-xs text-gray-700">
					<FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5 text-gray-500" fixed-width aria-hidden="true" />
					<span>{{ ctrans(":container is not deleted: it stays open, with the goods of the other orders in it. Only the items above leave it, and they can be put in a container again later.", { container: containerToRemove.reference }) }}</span>
				</p>

				<p v-if="removeError" class="text-xs text-red-600">{{ removeError }}</p>
			</div>

			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="containerToRemove = null">
						<template #icon>
							<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="red" key=3 full :icon="faExclamationTriangle" :label="ctrans('Take out of the container')" :loading="isRemoving" @click="removeFromContainer">
						<!-- <template #icon>
							<FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
						</template> -->
					</Button>
				</div>
			</template>
		</Dialog>
	</div>
</template>
