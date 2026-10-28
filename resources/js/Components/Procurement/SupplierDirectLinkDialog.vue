<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCopy, faLink } from "@fal"
import { faCheck } from "@fas"

library.add(faCopy, faLink, faCheck)

export interface RouteTarget {
	name: string
	parameters: Record<string, number>
}

export interface Share {
	can_create: boolean
	email: string | null
	months: number[]
	store_route: RouteTarget
	email_route: RouteTarget
	portal_url: string
	preview_url: string
}

const props = defineProps<{
	share: Share
}>()

const emit = defineEmits<{
	(e: "created"): void
}>()

const isOpen = ref(false)
const months = ref(0)
const isSaving = ref(false)
const createdUrl = ref<string | null>(null)
const createError = ref<string | null>(null)
const isCopied = ref(false)

const monthOptions = computed(() => props.share.months.map((count) => ({ label: count === 0 ? ctrans("Forever") : count === 1 ? ctrans("1 month") : ctrans(":count months", { count: String(count) }), value: count, icon: months.value === count ? "fas fa-check" : undefined })))

const open = () => {
	createdUrl.value = null
	createError.value = null
	isCopied.value = false
	isOpen.value = true
}

const create = async () => {
	isSaving.value = true
	createError.value = null
	try {
		const response = await axios.post(route(props.share.store_route.name, props.share.store_route.parameters), {
			months: months.value,
		})
		createdUrl.value = response.data.url
		emit("created")
	} catch (error: any) {
		createError.value = error.response?.data?.message ?? ctrans("The link could not be created.")
	} finally {
		isSaving.value = false
	}
}

const copy = async () => {
	await navigator.clipboard.writeText(createdUrl.value ?? "")
	isCopied.value = true
}
</script>

<template>
	<div>
		<Button v-if="share.can_create" type="tertiary" size="xs" :label="ctrans('Direct link')" @click="open">
			<template #icon><FontAwesomeIcon icon="fal fa-link" fixed-width aria-hidden="true" /></template>
		</Button>

		<Dialog v-model:visible="isOpen" modal dismissableMask :header="ctrans('Direct link')" :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }">
			<div class="space-y-4 text-sm text-gray-700">
				<p class="text-xs text-gray-500">
					{{ ctrans("For a supplier without email: make a link and send it yourself, by WhatsApp for example. Anyone with the link sees the supplier's page.") }}
				</p>

				<div v-if="createdUrl" class="space-y-2 rounded border border-green-200 bg-green-50 p-3">
					<p class="text-xs font-semibold text-green-800">{{ ctrans("Send this link to the supplier. It is shown only now.") }}</p>
					<div class="flex items-center gap-2">
						<InputText :model-value="createdUrl" readonly class="w-full font-mono text-xs" @focus="($event.target as HTMLInputElement).select()" />
						<Button type="tertiary" size="xs" :label="isCopied ? ctrans('Copied') : ctrans('Copy')" @click="copy">
							<template #icon><FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" /></template>
						</Button>
					</div>
				</div>

				<template v-else>
					<div class="flex flex-wrap items-center gap-3">
						<span class="text-xs font-semibold text-gray-600">{{ ctrans("Works for") }}</span>
						<SegmentedToggle v-model="months" :options="monthOptions" :aria-label="ctrans('Works for')" />
					</div>
					<p v-if="createError" class="text-xs text-red-600">{{ createError }}</p>
					<div class="flex justify-end">
						<Button type="tertiary" size="xs" :label="ctrans('Create link')" :loading="isSaving" @click="create" />
					</div>
				</template>
			</div>
		</Dialog>
	</div>
</template>
