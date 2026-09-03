<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import { getStyles } from "@/Composables/styles"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

defineProps<{
	fieldValue: any
	theme?: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

const isLoading = ref(false)
const listLoginError = ref<any>(null)
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

const registerUrl = computed(() => urlWithParams("/app/register", { tiktok_code: queryParam("tiktok_code") }))
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
	</div>
</template>
