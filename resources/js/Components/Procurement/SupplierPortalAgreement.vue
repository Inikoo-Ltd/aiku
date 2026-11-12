<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { reactive, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Checkbox from "primevue/checkbox"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SupplierAgreementStatusBadge from "@/Components/Procurement/SupplierAgreementStatusBadge.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileSignature, faCheck, faInfoCircle } from "@fal"

library.add(faFileSignature, faCheck, faInfoCircle)

export interface PortalAgreement {
	version: { id: number, version: number, title: string, body_html: string, published_at: string | null }
	status: { status: string, label: string, colour: string, icon: string, accepted_version: number | null, accepted_at: string | null }
	must_accept: boolean
	last_acceptance: { version: number, accepted_at: string, supplier_legal_name: string, authorised_person: string, position: string } | null
	changes: { version: number, change_summary: string | null, published_at: string | null }[]
	legal_name: string
	accept_route: string
}

const props = defineProps<{
	agreement: PortalAgreement
	preview?: boolean
}>()

const form = reactive({
	accept: false,
	supplier_legal_name: props.agreement.legal_name,
	authorised_person: "",
	position: "",
})
const errors = ref<Record<string, string>>({})
const isSending = ref(false)

function submit() {
	if (props.preview) {
		return
	}
	router.post(props.agreement.accept_route, { ...form, supplier_agreement_version_id: props.agreement.version.id }, {
		preserveScroll: true,
		preserveState: true,
		onStart: () => { isSending.value = true; errors.value = {} },
		onFinish: () => { isSending.value = false },
		onError: (formErrors) => { errors.value = formErrors as Record<string, string> },
	})
}
</script>

<template>
	<section class="rounded-lg border border-gray-200 bg-white shadow-sm">
		<header class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-3">
			<FontAwesomeIcon icon="fal fa-file-signature" class="text-gray-400" fixed-width aria-hidden="true" />
			<h2 class="text-sm font-semibold text-gray-900">{{ agreement.version.title }}</h2>
			<span class="text-xs text-gray-500">v{{ agreement.version.version }}</span>
			<SupplierAgreementStatusBadge :status="agreement.status" class="ml-auto" />
		</header>

		<div class="px-4 py-3 text-sm">
			<p v-if="agreement.status.status === 'accepted' && agreement.last_acceptance" class="text-green-800">
				{{ ctrans("Accepted v:version on :date by :person, :position.", {
					version: agreement.last_acceptance.version,
					date: useFormatTime(agreement.last_acceptance.accepted_at),
					person: agreement.last_acceptance.authorised_person,
					position: agreement.last_acceptance.position,
				}) }}
			</p>
			<p v-else-if="agreement.status.status === 'outdated'" class="text-amber-800">
				{{ ctrans("There is a new version to accept. You accepted v:version; please read this version and accept it below.", { version: agreement.status.accepted_version }) }}
			</p>
			<p v-else class="text-red-800">{{ ctrans("Not accepted yet. Please read the agreement and accept it below.") }}</p>

			<div v-if="agreement.changes.length" class="mt-2 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700">
				<div class="flex items-center gap-1 font-medium">
					<FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
					{{ ctrans("Changed since you accepted it. Your acceptance still counts.") }}
				</div>
				<p v-for="change in agreement.changes" :key="change.version" class="mt-1">v{{ change.version }}: {{ change.change_summary }}</p>
			</div>
		</div>

		<div class="supplier-agreement-body max-h-[60vh] overflow-y-auto border-y border-gray-100 px-4 py-3 text-sm text-gray-700" v-html="agreement.version.body_html"></div>
		<p class="px-4 pt-2 text-xs text-gray-400">{{ ctrans("The agreement is in English. The English text is the one you accept.") }}</p>

		<form v-if="agreement.must_accept" class="flex flex-col gap-3 px-4 py-4 text-sm" @submit.prevent="submit">
			<label class="flex items-start gap-2">
				<Checkbox v-model="form.accept" binary :disabled="preview" input-id="supplier-agreement-accept" class="mt-0.5" />
				<span class="text-gray-800">{{ ctrans("I have read and accept this Supplier Agreement and confirm that I am authorised to accept it on behalf of the supplier.") }} <span class="text-red-500">*</span></span>
			</label>
			<p v-if="errors.accept" class="text-xs text-red-600">{{ errors.accept }}</p>

			<div class="grid gap-3 md:grid-cols-3">
				<label class="flex flex-col gap-1">
					<span class="text-xs font-medium text-gray-700">{{ ctrans("Supplier legal name") }} <span class="text-red-500">*</span></span>
					<InputText v-model="form.supplier_legal_name" :disabled="preview" autocomplete="organization" fluid />
					<span v-if="errors.supplier_legal_name" class="text-xs text-red-600">{{ errors.supplier_legal_name }}</span>
				</label>
				<label class="flex flex-col gap-1">
					<span class="text-xs font-medium text-gray-700">{{ ctrans("Authorised person") }} <span class="text-red-500">*</span></span>
					<InputText v-model="form.authorised_person" :disabled="preview" autocomplete="name" fluid />
					<span v-if="errors.authorised_person" class="text-xs text-red-600">{{ errors.authorised_person }}</span>
				</label>
				<label class="flex flex-col gap-1">
					<span class="text-xs font-medium text-gray-700">{{ ctrans("Position") }} <span class="text-red-500">*</span></span>
					<InputText v-model="form.position" :disabled="preview" autocomplete="organization-title" fluid />
					<span v-if="errors.position" class="text-xs text-red-600">{{ errors.position }}</span>
				</label>
			</div>
			<p class="text-xs text-gray-500">{{ ctrans("The date is recorded when we receive your acceptance.") }}</p>

			<Button type="primary" full :label="ctrans('Accept the agreement')" :loading="isSending" :disabled="preview || !form.accept" @click="submit">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<p v-if="errors.supplier_agreement_version_id || errors.preview" class="text-xs text-red-600">{{ errors.supplier_agreement_version_id ?? errors.preview }}</p>
			<p v-if="preview" class="text-xs text-amber-700">{{ ctrans("Preview: the form cannot be sent from here.") }}</p>
		</form>
	</section>
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
