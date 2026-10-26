<script setup lang="ts">
import { ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope, faLock } from "@fal"

library.add(faEnvelope, faLock)

defineOptions({ layout: (_h: unknown, page: unknown) => page })

const props = defineProps<{
	title: string
	link_requested: boolean
	days: number
	routes: { send: string }
	errors?: Record<string, string>
}>()

const email = ref("")
const isSending = ref(false)

const send = () => {
	isSending.value = true
	router.post(props.routes.send, { email: email.value }, { preserveScroll: true, onFinish: () => (isSending.value = false) })
}
</script>

<template>
	<Head :title="title" />
	<div class="flex min-h-[100dvh] items-start justify-center bg-gray-50 px-4 pt-16">
		<section class="w-full max-w-md space-y-4 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
			<div>
				<h1 class="flex items-center gap-2 text-base font-semibold text-gray-800">
					<FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" />
					{{ ctrans("Supplier sign in") }}
				</h1>
				<p class="mt-1 text-sm text-gray-600">
					{{ ctrans("Enter the email your company has with Ancient Wisdom. We email you a private link to your page. No password needed.") }}
				</p>
			</div>

			<div v-if="link_requested" class="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
				{{ ctrans("If this email is registered for your company, a link is on its way. It works for :days days. Check your spam folder if it does not arrive in a few minutes.", { days: String(days) }) }}
			</div>

			<form class="space-y-3" @submit.prevent="send">
				<InputText v-model="email" type="email" autocomplete="email" class="w-full" :placeholder="ctrans('Your email')" :aria-label="ctrans('Your email')" />
				<p v-if="errors?.email" class="text-xs text-red-600">{{ errors.email }}</p>
				<div class="flex justify-end">
					<Button type="tertiary" size="xs" :label="ctrans('Email me my link')" :loading="isSending" :disabled="!email" @click="send">
						<template #icon><FontAwesomeIcon icon="fal fa-envelope" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</form>

			<p class="text-xs text-gray-400">{{ ctrans("Your email is not on file, or it changed? Ask your contact at Ancient Wisdom to add it.") }}</p>
		</section>
	</div>
</template>
