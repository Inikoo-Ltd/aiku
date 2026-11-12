<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, useForm, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import InputText from "primevue/inputtext"
import Textarea from "primevue/textarea"
import ToggleSwitch from "primevue/toggleswitch"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSave, faPaperPlane, faTimes, faExclamationTriangle } from "@fal"

library.add(faSave, faPaperPlane, faTimes, faExclamationTriangle)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	version: {
		id: number
		version: number
		title: string
		body: string
		body_html: string
		change_summary: string | null
		requires_reacceptance: boolean
		state: { value: string, label: string }
		published_at: string | null
		published_by: string | null
		body_hash: string | null
		number_acceptances: number
	}
	is_first: boolean
	can_edit: boolean
	routes: { update: { name: string, parameters: object }, publish: { name: string, parameters: object } } | null
}>()

const form = useForm({
	title: props.version.title,
	body: props.version.body,
	change_summary: props.version.change_summary ?? "",
	requires_reacceptance: props.version.requires_reacceptance,
})

const isConfirmingPublish = ref(false)
const isPublishing = ref(false)
const publishErrors = ref<Record<string, string>>({})

function save(onSuccess?: () => void) {
	if (!props.routes) {
		return
	}
	form.patch(route(props.routes.update.name, props.routes.update.parameters), {
		preserveScroll: true,
		onSuccess: () => onSuccess?.(),
	})
}

function publish() {
	if (!props.routes) {
		return
	}
	save(() => {
		router.post(route(props.routes!.publish.name, props.routes!.publish.parameters), {}, {
			preserveScroll: true,
			onStart: () => { isPublishing.value = true; publishErrors.value = {} },
			onFinish: () => { isPublishing.value = false },
			onError: (errors) => { publishErrors.value = errors as Record<string, string> },
			onSuccess: () => { isConfirmingPublish.value = false },
		})
	})
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="grid gap-4 px-4 py-4 xl:grid-cols-2">
		<section v-if="can_edit" class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 text-sm">
			<label class="flex flex-col gap-1">
				<span class="font-medium text-gray-700">{{ ctrans("Title") }} <span class="text-red-500">*</span></span>
				<InputText v-model="form.title" fluid />
				<span v-if="form.errors.title" class="text-xs text-red-600">{{ form.errors.title }}</span>
			</label>
			<label class="flex flex-col gap-1">
				<span class="font-medium text-gray-700">{{ ctrans("Agreement text (Markdown, English)") }} <span class="text-red-500">*</span></span>
				<Textarea v-model="form.body" rows="24" class="font-mono text-xs" fluid />
				<span v-if="form.errors.body || publishErrors.body" class="text-xs text-red-600">{{ form.errors.body ?? publishErrors.body }}</span>
			</label>
			<label v-if="!is_first" class="flex items-center gap-2">
				<ToggleSwitch v-model="form.requires_reacceptance" />
				<span class="text-gray-700">{{ ctrans("Suppliers must accept this version again") }}</span>
			</label>
			<p v-if="!is_first && !form.requires_reacceptance" class="text-xs text-gray-500">{{ ctrans("For a typo or a clarification: suppliers keep their acceptance and are shown what changed.") }}</p>
			<label v-if="!is_first" class="flex flex-col gap-1">
				<span class="font-medium text-gray-700">
					{{ ctrans("What changed") }} <span v-if="!form.requires_reacceptance" class="text-red-500">*</span>
				</span>
				<Textarea v-model="form.change_summary" rows="3" fluid />
				<span v-if="form.errors.change_summary || publishErrors.change_summary" class="text-xs text-red-600">{{ form.errors.change_summary ?? publishErrors.change_summary }}</span>
			</label>

			<div class="flex flex-wrap items-center gap-2">
				<Button type="tertiary" :label="ctrans('Save draft')" :loading="form.processing && !isConfirmingPublish" @click="save()">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-save" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button v-if="!isConfirmingPublish" type="tertiary" :label="ctrans('Publish')" @click="isConfirmingPublish = true">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-paper-plane" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<span v-if="form.recentlySuccessful" class="text-xs text-green-700">{{ ctrans("Saved") }}</span>
			</div>

			<div v-if="isConfirmingPublish" class="rounded border border-amber-200 bg-amber-50 p-3 text-amber-900">
				<p class="flex items-start gap-2">
					<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5" fixed-width aria-hidden="true" />
					<span v-if="is_first">{{ ctrans("Every supplier will be shown as Not accepted until it accepts this version in its portal. Once published the text cannot be changed.") }}</span>
					<span v-else-if="form.requires_reacceptance">{{ ctrans("Every supplier that accepted an earlier version will be shown as Outdated until it accepts this one. Once published the text cannot be changed.") }}</span>
					<span v-else>{{ ctrans("Suppliers keep their acceptance and are shown what changed. Once published the text cannot be changed.") }}</span>
				</p>
				<div class="mt-2 flex gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isConfirmingPublish = false">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="tertiary" :label="ctrans('Publish version :version', { version: version.version })" :loading="isPublishing || form.processing" @click="publish">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-paper-plane" class="text-amber-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
				<p v-if="publishErrors.version" class="mt-1 text-xs text-red-600">{{ publishErrors.version }}</p>
			</div>
		</section>

		<section class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700">
			<div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
				<span class="rounded-full border border-gray-200 px-2 py-0.5">{{ version.state.label }}</span>
				<span v-if="version.published_at">{{ ctrans("Published :date", { date: useFormatTime(version.published_at) }) }}<template v-if="version.published_by"> · {{ version.published_by }}</template></span>
				<span v-if="version.state.value !== 'draft'">{{ ctrans(":count suppliers accepted it", { count: version.number_acceptances }) }}</span>
			</div>
			<p v-if="version.change_summary && !can_edit" class="mb-3 rounded bg-gray-50 px-3 py-2 text-xs">{{ ctrans("What changed") }}: {{ version.change_summary }}</p>
			<h2 class="mb-2 text-base font-semibold text-gray-900">{{ can_edit ? form.title : version.title }}</h2>
			<p v-if="can_edit" class="mb-2 text-xs text-gray-400">{{ ctrans("Preview of the last saved text") }}</p>
			<div class="supplier-agreement-body" v-html="version.body_html"></div>
			<p v-if="version.body_hash" class="mt-4 break-all font-mono text-[10px] text-gray-400">SHA-256 {{ version.body_hash }}</p>
		</section>
	</div>
</template>

<style scoped>
.supplier-agreement-body :deep(h1),
.supplier-agreement-body :deep(h2),
.supplier-agreement-body :deep(h3) {
	margin: 1rem 0 0.25rem;
	font-weight: 600;
	color: #111827;
}
.supplier-agreement-body :deep(p) {
	margin: 0 0 0.5rem;
}
.supplier-agreement-body :deep(ul) {
	margin: 0 0 0.5rem 1.25rem;
	list-style: disc;
}
.supplier-agreement-body :deep(ol) {
	margin: 0 0 0.5rem 1.25rem;
	list-style: decimal;
}
</style>
