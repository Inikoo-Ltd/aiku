<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-12 12:06
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import axios from "axios"
import { debounce } from "lodash-es"
import { router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faExclamationTriangle, faExternalLink } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useTruncate } from "@/Composables/useTruncate"
import { routeType } from "@/types/route"

library.add(faCheck, faTimes, faExclamationTriangle, faExternalLink)

export interface RenameReference {
	availability_route: routeType
	update_route: routeType
}

interface PurchaseOrderMatch {
	reference: string
	state: string
	is_deleted: boolean
	url: string | null
}

interface Availability {
	reference: string
	is_unchanged: boolean
	is_available: boolean
	conflict: PurchaseOrderMatch | null
	slug: string | null
	alternatives: string[]
	similar: PurchaseOrderMatch[]
}

const props = defineProps<{
	reference: string
	slug: string
	renameReference: RenameReference | null
}>()

const isEditing = ref(false)
const draft = ref(props.reference)
const availability = ref<Availability | null>(null)
const isChecking = ref(false)
const isSaving = ref(false)
const errorMessage = ref<string | null>(null)
const inputElement = ref<InstanceType<typeof InputText> | null>(null)
let latestCheck = 0

const trimmedDraft = computed(() => draft.value.trim())

const isCurrentAvailability = computed(() => availability.value?.reference === trimmedDraft.value)

const canSave = computed(() =>
	!isSaving.value
	&& !isChecking.value
	&& trimmedDraft.value !== ""
	&& trimmedDraft.value !== props.reference
	&& isCurrentAvailability.value
	&& !!availability.value?.is_available
)

const slugChanges = computed(() => !!availability.value?.slug && availability.value.slug !== props.slug)

const startEditing = async () => {
	if (!props.renameReference || isEditing.value) {
		return
	}
	draft.value = props.reference
	availability.value = null
	errorMessage.value = null
	isEditing.value = true
	await nextTick()
	const input = (inputElement.value as unknown as { $el: HTMLInputElement } | null)?.$el
	input?.focus()
	input?.select()
	runCheck()
}

const cancel = () => {
	isEditing.value = false
	draft.value = props.reference
	availability.value = null
	errorMessage.value = null
}

const runCheck = async () => {
	if (!props.renameReference || trimmedDraft.value === "") {
		availability.value = null
		isChecking.value = false
		return
	}

	const checkNumber = ++latestCheck
	isChecking.value = true
	try {
		const { data } = await axios.get(
			route(props.renameReference.availability_route.name, props.renameReference.availability_route.parameters),
			{ params: { reference: trimmedDraft.value } }
		)
		if (checkNumber === latestCheck) {
			availability.value = data
		}
	} catch (error: any) {
		if (checkNumber === latestCheck) {
			availability.value = null
			errorMessage.value = error?.response?.data?.message ?? ctrans("Could not check the reference")
		}
	} finally {
		if (checkNumber === latestCheck) {
			isChecking.value = false
		}
	}
}

const debouncedCheck = debounce(runCheck, 350)

watch(draft, () => {
	if (!isEditing.value) {
		return
	}
	errorMessage.value = null
	isChecking.value = true
	debouncedCheck()
})

const useSuggestion = (reference: string) => {
	draft.value = reference
}

const save = async () => {
	if (!canSave.value || !props.renameReference) {
		return
	}

	isSaving.value = true
	errorMessage.value = null
	try {
		const { data } = await axios.patch(
			route(props.renameReference.update_route.name, props.renameReference.update_route.parameters),
			{ reference: trimmedDraft.value }
		)
		isEditing.value = false
		if (data.slug !== props.slug) {
			const currentRoute = route().current() as string
			router.visit(route(currentRoute, { ...route().params, purchaseOrder: data.slug }), { replace: true })
		} else {
			router.reload()
		}
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.errors?.reference?.[0]
			?? error?.response?.data?.message
			?? ctrans("Could not rename the purchase order")
	} finally {
		isSaving.value = false
	}
}
</script>

<template>
	<div class="relative inline-flex">
		<span
			v-if="!isEditing"
			class="inline-block whitespace-nowrap"
			:class="renameReference ? 'cursor-text rounded px-1 -mx-1 hover:bg-[var(--app-accent-soft)]' : ''"
			v-tooltip="renameReference ? ctrans('Double-click to rename') : (reference.length > 30 ? reference : undefined)"
			@dblclick="startEditing">
			{{ useTruncate(reference, 30) }}
		</span>

		<template v-else>
			<div class="flex items-center gap-1.5">
				<InputText
					ref="inputElement"
					v-model="draft"
					size="small"
					class="w-80 max-w-[60vw] font-semibold"
					:invalid="isCurrentAvailability && !!availability && !availability.is_available"
					:disabled="isSaving"
					@keydown.enter.prevent="save"
					@keydown.esc.prevent="cancel" />
				<Button type="tertiary" size="s" :tooltip="ctrans('Cancel')" :disabled="isSaving" @click="cancel">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" size="s" :label="ctrans('Rename')" :loading="isSaving" :disabled="!canSave" @click="save">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>

			<div class="absolute left-0 top-full z-30 mt-2 w-[30rem] max-w-[90vw] space-y-3 rounded-md border border-gray-200 bg-white p-3 text-sm font-normal leading-normal tracking-normal text-gray-700 shadow-lg">
				<div class="flex items-center gap-1.5">
					<template v-if="isChecking || !isCurrentAvailability">
						<LoadingIcon v-if="trimmedDraft !== ''" />
						<span class="text-gray-500">{{ trimmedDraft === "" ? ctrans("Type the new reference") : ctrans("Checking availability") }}</span>
					</template>
					<template v-else-if="availability?.is_unchanged">
						<span class="text-gray-500">{{ ctrans("This is the current reference") }}</span>
					</template>
					<template v-else-if="availability?.is_available">
						<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
						<span class="text-green-700">{{ ctrans("Available") }}</span>
					</template>
					<template v-else-if="availability?.conflict">
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
						<span class="text-red-600">
							{{ ctrans("Already used by") }}
							<a v-if="availability.conflict.url" :href="availability.conflict.url" target="_blank" class="whitespace-nowrap font-semibold underline">{{ availability.conflict.reference }}</a>
							<span v-else class="whitespace-nowrap font-semibold">{{ availability.conflict.reference }}</span>
							({{ availability.conflict.state }})
						</span>
					</template>
					<template v-else-if="availability">
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
						<span class="text-red-600">{{ ctrans("The reference needs at least one letter or number.") }}</span>
					</template>
				</div>

				<div v-if="isCurrentAvailability && availability?.is_available && slugChanges" class="space-y-0.5 text-xs">
					<div class="text-gray-500">
						{{ ctrans("New URL") }}: <span class="whitespace-nowrap font-mono text-gray-700">…/{{ availability.slug }}</span>
					</div>
					<div class="flex items-center gap-1 text-amber-600">
						<FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
						{{ ctrans("Links saved with the old URL will stop working.") }}
					</div>
				</div>

				<div v-if="isCurrentAvailability && availability?.alternatives.length">
					<div class="mb-1 text-xs font-semibold text-gray-500">{{ ctrans("Free alternatives") }}</div>
					<div class="flex flex-wrap gap-1.5">
						<button
							v-for="alternative in availability.alternatives"
							:key="alternative"
							type="button"
							class="whitespace-nowrap rounded border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] px-2 py-0.5 text-xs text-[var(--app-accent-deep)] hover:border-[var(--app-accent)]"
							@click="useSuggestion(alternative)">
							{{ alternative }}
						</button>
					</div>
				</div>

				<div v-if="isCurrentAvailability && availability?.similar.length">
					<div class="mb-1 text-xs font-semibold text-gray-500">{{ ctrans("Purchase orders with a similar reference") }}</div>
					<ul class="max-h-48 space-y-0.5 overflow-y-auto text-xs">
						<li v-for="match in availability.similar" :key="match.reference" class="flex items-center justify-between gap-3">
							<a v-if="match.url" :href="match.url" target="_blank" class="whitespace-nowrap text-[var(--app-accent)] hover:underline">
								{{ match.reference }}
								<FontAwesomeIcon icon="fal fa-external-link" class="text-[0.7rem]" fixed-width aria-hidden="true" />
							</a>
							<span v-else class="whitespace-nowrap">{{ match.reference }}</span>
							<span class="shrink-0 text-gray-400">{{ match.state }}</span>
						</li>
					</ul>
				</div>

				<div v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</div>

				<div class="text-xs text-gray-400">{{ ctrans("Enter to save, Esc to cancel") }}</div>
			</div>
		</template>
	</div>
</template>
