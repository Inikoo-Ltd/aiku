<script setup lang="ts">
import { computed, inject, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { googleTokenLogin } from "vue3-google-login"
import { getStyles } from "@/Composables/styles"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Modal from "@/Components/Utils/Modal.vue"

const props = defineProps<{
	fieldValue: any
	theme?: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

const layout = inject<any>("layout", {})

const isLoading = ref(false)
const isLoadingGoogle = ref(false)
const listLoginError = ref<any>(null)
const googleAccount = ref<any>(null)
const isOpenGoogleRegistration = ref(false)
const form = ref({
	username: "",
	password: "",
	remember: false,
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

const googleClientId = computed(() => layout?.iris?.google?.client_id)
const isGoogleLoginVisible = computed(() => !!googleClientId.value && props.fieldValue?.login?.google?.visible !== false)

const registerUrl = computed(() => urlWithParams("/app/register", { tiktok_code: queryParam("tiktok_code") }))
const registerWithGoogleUrl = computed(() =>
	urlWithParams("/app/register-from-google", { google_access_token: googleAccount.value?.google_access_token })
)
const forgotPasswordUrl = computed(() => urlWithParams("/app/reset-password-send", { tiktok_code: queryParam("tiktok_code") }))

const submit = async () => {
	if (isLoading.value) {
		return
	}

	isLoading.value = true

	try {
		const response = await axios.post(
			urlWithParams("/app/login", { ref: queryParam("ref") }),
			{
				username: form.value.username,
				password: form.value.password,
				remember: form.value.remember,
				tiktok_code: queryParam("tiktok_code"),
			}
		)

		form.value.password = ""
		window.location.href = response.data ? `${response.data}` : "/app/dashboard"
	} catch (error: any) {
		form.value.password = ""
		isLoading.value = false
		listLoginError.value = error.response?.data
	}
}

const clearError = () => {
	listLoginError.value = null
}

const getRedirectUrl = async () => {
	try {
		const response = await axios.get(urlWithParams("/json/canonical-redirect", { ref: queryParam("ref") }))

		return response.data?.redirect_url || "/"
	} catch (error: any) {
		return "/"
	}
}

const loginWithGoogle = async () => {
	if (isLoadingGoogle.value || !googleClientId.value) {
		return
	}

	let googleResponse: any
	try {
		googleResponse = await googleTokenLogin({ clientId: googleClientId.value })
	} catch (error: any) {
		return
	}

	isLoadingGoogle.value = true

	try {
		const response = await axios.post("/app/login-google", {
			google_access_token: googleResponse.access_token,
			tiktok_code: queryParam("tiktok_code"),
		})

		if (response.data?.logged_in) {
			window.location.href = await getRedirectUrl()
			return
		}

		googleAccount.value = response.data?.google_user
		isOpenGoogleRegistration.value = true
	} catch (error: any) {
		notify({
			title: trans("Something went wrong"),
			text: trans("Failed to login with Google. Please contact administrator."),
			type: "error",
		})
	}

	isLoadingGoogle.value = false
}
</script>

<template>
	<div
		:id="fieldValue?.id ? fieldValue?.id : 'login'"
		component="login"
		:style="getStyles(fieldValue?.container?.properties, screenType)">
		<div class="mx-auto grid w-full max-w-6xl grid-cols-1 gap-x-16 gap-y-14 px-4 md:grid-cols-2">
			<div class="mx-auto w-full max-w-md">
				<div class="editor-class text-xl font-semibold sm:text-2xl" v-html="fieldValue?.login?.title" />

				<form class="mt-8 space-y-5" @submit.prevent="submit">
					<div>
						<label for="login-username" class="block text-sm font-semibold">
							{{ fieldValue?.login?.username?.label }}
						</label>
						<input
							id="login-username"
							v-model="form.username"
							name="username"
							type="text"
							autocomplete="username"
							required
							class="mt-2 w-full rounded-sm border border-gray-400 bg-white px-3 py-2 text-base text-gray-700 outline-none focus:border-gray-600"
							:class="listLoginError?.errors?.username ? 'errorShake' : ''"
							:placeholder="fieldValue?.login?.username?.placeholder"
							:disabled="isLoading"
							@input="clearError" />
						<p v-if="listLoginError?.errors?.username" class="mt-2 text-sm text-red-600">
							{{ listLoginError?.errors?.username[0] }}
						</p>
					</div>

					<div>
						<label for="login-password" class="block text-sm font-semibold">
							{{ fieldValue?.login?.password?.label }}
						</label>
						<input
							id="login-password"
							v-model="form.password"
							name="password"
							type="password"
							autocomplete="current-password"
							required
							class="mt-2 w-full rounded-sm border border-gray-400 bg-white px-3 py-2 text-base text-gray-700 outline-none focus:border-gray-600"
							:class="listLoginError?.errors?.password ? 'errorShake' : ''"
							:placeholder="fieldValue?.login?.password?.placeholder"
							:disabled="isLoading"
							@input="clearError" />
						<p v-if="listLoginError?.errors?.password" class="mt-2 text-sm text-red-600">
							{{ listLoginError?.errors?.password[0] }}
						</p>

						<div class="mt-2 flex items-center justify-between">
							<label v-if="fieldValue?.login?.remember?.visible" class="flex cursor-pointer select-none items-center gap-x-2 text-sm">
								<input v-model="form.remember" type="checkbox" name="remember" class="cursor-pointer" />
								{{ fieldValue?.login?.remember?.text }}
							</label>
							<span v-else />

							<a :href="forgotPasswordUrl" class="text-sm underline">
								{{ fieldValue?.login?.forgot_password?.text }}
							</a>
						</div>
					</div>

					<p v-if="listLoginError?.message" class="text-sm italic text-red-600">
						*{{ listLoginError?.message }}
					</p>

					<button
						type="submit"
						class="relative flex w-full cursor-pointer items-center justify-center gap-x-2 rounded-sm transition duration-75 ease-in-out disabled:opacity-70"
						:style="getStyles(fieldValue?.login?.button?.container?.properties, screenType)"
						:disabled="isLoading">
						{{ fieldValue?.login?.button?.text }}
						<LoadingIcon v-if="isLoading" />
					</button>

					<div v-if="isGoogleLoginVisible" class="space-y-3">
						<div class="text-center text-sm">
							{{ fieldValue?.login?.google?.note || trans("or use your Google account to login") }}
						</div>

						<button
							type="button"
							class="relative flex w-full cursor-pointer items-center justify-center gap-x-2 rounded-sm border border-gray-700 bg-white px-4 py-2 text-gray-800 transition duration-150 ease-in-out hover:bg-gray-100 disabled:opacity-70"
							:disabled="isLoadingGoogle"
							@click="loginWithGoogle">
							<svg class="absolute left-4 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
								<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
								<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
								<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
								<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
							</svg>
							{{ fieldValue?.login?.google?.text || trans("Login with Google") }}
							<LoadingIcon v-if="isLoadingGoogle" />
						</button>
					</div>

					<div class="editor-class text-sm" v-html="fieldValue?.login?.help" />
				</form>
			</div>

			<div class="mx-auto w-full max-w-md">
				<div class="editor-class text-xl font-semibold sm:text-2xl" v-html="fieldValue?.register?.title" />

				<div class="mt-6 flex justify-center">
					<a
						:href="registerUrl"
						class="inline-flex cursor-pointer items-center justify-center rounded-sm transition duration-75 ease-in-out"
						:style="getStyles(fieldValue?.register?.button?.container?.properties, screenType)">
						{{ fieldValue?.register?.button?.text }}
					</a>
				</div>

				<div class="editor-class mt-2 text-sm" v-html="fieldValue?.register?.note" />

				<div class="editor-class mt-8 text-sm" v-html="fieldValue?.register?.description" />

				<div
					class="editor-class mt-2 text-sm"
					:style="getStyles(fieldValue?.register?.benefits?.container?.properties, screenType)"
					v-html="fieldValue?.register?.benefits?.text" />
			</div>
		</div>

		<Modal :isOpen="isOpenGoogleRegistration" width="max-w-lg w-full" @onClose="isOpenGoogleRegistration = false">
			<div class="p-6">
				<h2 class="mb-2 text-center text-lg">
					{{ trans("Hello") }}, <span class="font-semibold">{{ googleAccount?.name }}</span>!
				</h2>

				<div class="mb-4 text-center text-gray-600">
					<div class="mb-3 italic">{{ googleAccount?.email }}</div>
					<p>{{ trans("This email was not found in our database") }}</p>
					<p>{{ trans("Do you want to create an account?") }}</p>
				</div>

				<div class="flex justify-center gap-x-3">
					<button
						type="button"
						class="cursor-pointer rounded-sm border border-gray-400 px-4 py-2 text-sm"
						@click="isOpenGoogleRegistration = false">
						{{ trans("No, thanks") }}
					</button>

					<a
						:href="registerWithGoogleUrl"
						class="cursor-pointer rounded-sm px-4 py-2 text-sm"
						:style="getStyles(fieldValue?.register?.button?.container?.properties, screenType)">
						{{ trans("Yes") }}
					</a>
				</div>
			</div>
		</Modal>
	</div>
</template>
