<script setup lang="ts">
import { computed, ref } from "vue"
import SupplierPortalLanguages from "@/Components/Procurement/SupplierPortalLanguages.vue"
import { Head, router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope } from "@fal"
import GrpAuth from "@/Layouts/GrpAuth.vue"

library.add(faEnvelope)

defineOptions({ layout: (_h: unknown, page: unknown) => page })

const props = defineProps<{
	title: string
	link_requested: boolean
	days: number
	company: string
	language: string
	routes: { send: string }
	errors?: Record<string, string>
}>()

const email = ref("")
const isSending = ref(false)

const isValidEmail = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim()))

const send = () => {
	if (!isValidEmail.value) {
		return
	}
	isSending.value = true
	router.post(props.routes.send, { email: email.value }, { preserveScroll: true, onFinish: () => (isSending.value = false) })
}
</script>

<template>
	<GrpAuth>
		<Head :title="title" />
		<form class="space-y-6" @submit.prevent="send">
			<div>
				<h1 class="text-base font-semibold text-gray-800">{{ ctrans("Supplier sign in") }}</h1>
				<p class="mt-1 min-h-[3.75rem] text-sm text-gray-600">
					{{ ctrans("Enter the email your company has with :company. We email you a private link to your page. No password needed.", { company }) }}
				</p>
			</div>

			<div v-if="link_requested" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
				{{ ctrans("If this email is registered for your company, a link is on its way. It works for :days days. Check your spam folder if it does not arrive in a few minutes.", { days: String(days) }) }}
			</div>

			<div>
				<label for="supplier-email" class="block text-sm font-medium text-gray-700">{{ ctrans("Email") }}</label>
				<InputText id="supplier-email" v-model="email" type="email" autocomplete="email" autofocus class="mt-1 w-full sm:text-sm" :placeholder="ctrans('Your email')" :invalid="!!errors?.email" />
				<p v-if="errors?.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
			</div>

			<div class="space-y-3">
				<Button
					full
					type="indigo"
					:label="isValidEmail ? ctrans('Email me my link') : ctrans('Enter your email')"
					:loading="isSending"
					:disabled="!isValidEmail || isSending"
					:class="isValidEmail ? '' : 'opacity-50'"
					@click.prevent="send">
					<template #icon><FontAwesomeIcon icon="fal fa-envelope" fixed-width aria-hidden="true" /></template>
				</Button>
				<p class="min-h-8 text-xs text-gray-500">{{ ctrans("Your email is not on file, or it changed? Ask your contact at :company to add it.", { company }) }}</p>
			</div>
		</form>

		<template #below>
			<SupplierPortalLanguages :language="language" class="mt-4" />
		</template>
	</GrpAuth>
</template>
