<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle } from "@fal"
import { getStyles } from "@/Composables/styles"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faCheckCircle)

defineProps<{
	fieldValue: any
	theme?: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

const isLoading = ref(false)
const isResetLinkSent = ref(false)
const errorMessage = ref<string | null>(null)
const sentToEmail = ref("")
const form = ref({
	email: "",
})

const queryParam = (name: string) => {
	if (typeof window === "undefined") {
		return null
	}

	return new URLSearchParams(window.location.search).get(name)
}

const urlWithParams = (path: string, params: Record<string, string | null>) => {
	const query = Object.entries(params)
		.filter(([, value]) => !!value)
		.map(([key, value]) => `${key}=${encodeURIComponent(value as string)}`)
		.join("&")

	return query ? `${path}?${query}` : path
}

const loginUrl = computed(() => urlWithParams("/app/login", { tiktok_code: queryParam("tiktok_code") }))

const clearError = () => {
	errorMessage.value = null
}

const submit = async () => {
	if (isLoading.value) {
		return
	}

	isLoading.value = true
	errorMessage.value = null

	try {
		await axios.post("/app/reset-password-send", {
			email: form.value.email,
		})

		sentToEmail.value = form.value.email
		isResetLinkSent.value = true
	} catch (error: any) {
		const responseErrors = error.response?.data?.errors?.email

		errorMessage.value = Array.isArray(responseErrors)
			? responseErrors[0]
			: responseErrors || error.response?.data?.message || trans("Something went wrong")
	}

	isLoading.value = false
}
</script>

<template>
	<div
		:id="fieldValue?.id ? fieldValue?.id : 'forgot-password'"
		component="forgot-password"
		:style="getStyles(fieldValue?.container?.properties, screenType)">
		<div class="mx-auto w-full max-w-md">
			<template v-if="!isResetLinkSent">
				<div class="editor-class text-xl font-semibold sm:text-2xl" v-html="fieldValue?.forgot_password?.title" />

				<div
					class="mt-6 overflow-hidden"
					:style="getStyles(fieldValue?.forgot_password?.card?.container?.properties, screenType)">
					<div class="editor-class text-sm" v-html="fieldValue?.forgot_password?.description" />

					<form class="mt-6 space-y-5" @submit.prevent="submit">
						<div>
							<label for="forgot-password-email" class="block text-sm font-semibold">
								{{ fieldValue?.forgot_password?.email?.label || trans("Email") }}
							</label>
							<input
								id="forgot-password-email"
								v-model="form.email"
								name="email"
								type="email"
								autocomplete="email"
								required
								autofocus
								class="mt-2 w-full rounded-sm border border-gray-400 bg-white px-3 py-2 text-base text-gray-700 outline-none focus:border-gray-600"
								:class="errorMessage ? 'errorShake' : ''"
								:placeholder="fieldValue?.forgot_password?.email?.placeholder"
								:disabled="isLoading"
								@input="clearError" />
							<p v-if="errorMessage" class="mt-2 text-sm italic text-red-600">
								{{ errorMessage }}
							</p>
						</div>

						<button
							type="submit"
							class="relative flex w-full cursor-pointer items-center justify-center gap-x-2 rounded-sm transition duration-75 ease-in-out disabled:opacity-70"
							:style="getStyles(fieldValue?.forgot_password?.button?.container?.properties, screenType)"
							:disabled="isLoading">
							{{ fieldValue?.forgot_password?.button?.text || trans("Email Password Reset Link") }}
							<LoadingIcon v-if="isLoading" />
						</button>
					</form>
				</div>
			</template>

			<template v-else>
				<div
					class="overflow-hidden"
					:style="getStyles(fieldValue?.forgot_password?.card?.container?.properties, screenType)">
					<div class="text-center">
						<FontAwesomeIcon icon="fal fa-check-circle" class="text-4xl text-green-500" fixed-width aria-hidden="true" />
					</div>

					<div
						class="editor-class mt-4 text-xl font-semibold"
						v-html="fieldValue?.forgot_password?.success?.title" />

					<div class="editor-class mt-2 text-sm" v-html="fieldValue?.forgot_password?.success?.description" />

					<div class="mt-2 text-center text-sm font-semibold">
						{{ sentToEmail }}
					</div>
				</div>
			</template>

			<div class="editor-class mt-6 text-sm" v-html="fieldValue?.forgot_password?.login?.note" />

			<div v-if="fieldValue?.forgot_password?.login?.text" class="mt-2 text-center text-sm">
				<a :href="loginUrl" class="underline">
					{{ fieldValue?.forgot_password?.login?.text }}
				</a>
			</div>
		</div>
	</div>
</template>
