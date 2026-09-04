<script setup lang="ts">
import { onMounted, provide, ref } from "vue"
import axios from "axios"
import { useForm } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { Checkbox } from "primevue"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope } from "@far"
import { faBuilding, faGlobe, faInfoCircle, faPhone, faUser } from "@fal"
import { faAsterisk, faExclamationTriangle } from "@fas"
import { getStyles } from "@/Composables/styles"
import PureInput from "@/Components/Pure/PureInput.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import FieldStandaloneRegistration from "@/Pages/Retina/Auth/Field/FieldStandaloneRegistration.vue"
import { getRefRedirect } from "@/Composables/Retina/useGetRedirectUrl"
import { buildRegistrationUserData, pushGtmEventAndWaitForTags } from "@/Composables/useGtm"
import { get } from "lodash-es"

library.add(faEnvelope, faUser, faAsterisk, faExclamationTriangle, faInfoCircle, faPhone, faBuilding, faGlobe)

defineProps<{
	fieldValue: any
	theme?: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

const isLoadingData = ref(true)
const countriesAddressData = ref<any>({})
const polls = ref<any[]>([])
const requiresPhoneNumber = ref(false)
const registrationSettings = ref<any>({})

const form = useForm({
	contact_name: "",
	email: "",
	phone: "",
	company_name: "",
	contact_website: "",
	password: "",
	password_confirmation: "",
	contact_address: {},
	poll_replies: [],
	is_opt_in: false,
	interest: [],
	tax_number: "",
})

const isLoading = ref(false)
const isAgreeTnc = ref(false)
const isErrorTnc = ref(false)
const isModalRemoveScript = ref(false)
const isModalRemoveHtml = ref(false)

const registrationWarning = ref({})
provide("registrationWarning", registrationWarning)

const queryParam = (name: string) => {
	if (typeof window === "undefined") {
		return null
	}

	return new URLSearchParams(window.location.search).get(name)
}

onMounted(async () => {
	try {
		const response = await axios.get("/json/registration-data")

		countriesAddressData.value = response.data?.countriesAddressData || {}
		polls.value = response.data?.polls || []
		requiresPhoneNumber.value = !!response.data?.requiresPhoneNumber
		registrationSettings.value = response.data?.registration_settings || {}

		form.poll_replies = polls.value.map((poll: any) => ({
			...poll,
			answer: poll.type === "option" ? null : "",
		}))
		form.is_opt_in = !!registrationSettings.value?.marketing_opt_in_default
	} catch (error: any) {
		countriesAddressData.value = {}
	}

	isLoadingData.value = false
})

const isUserInputPassed = (dataToCheck: {}) => {
	for (const key in dataToCheck) {
		const inputValue = dataToCheck[key]
		if (/<script>/i.test(inputValue)) {
			isModalRemoveScript.value = true
			form.errors[key] = "Script tags are not allowed."
			return true
		}
	}

	for (const key in dataToCheck) {
		const inputValue = dataToCheck[key]
		if (/<[^>]+>/i.test(inputValue) && !/<script>/i.test(inputValue)) {
			isModalRemoveHtml.value = true
			form.errors[key] = "HTML tags are not allowed."
			return true
		}
	}
}

const submit = async () => {
	if (isLoading.value) {
		return
	}

	const fieldsOfSelectedCountry = get(
		countriesAddressData.value,
		[get(form, ["contact_address", "country_id"], []), "fields"],
		{}
	)

	if (requiresPhoneNumber.value && !form.phone) {
		form.setError("phone", "Phone number is required")
		return
	}

	let isAddressFieldFailedPass = false
	for (const [fieldName, fieldData] of Object.entries(fieldsOfSelectedCountry)) {
		if (fieldData.required && !get(form, ["contact_address", fieldName])) {
			form.setError(fieldName, `${fieldName} is required`)
			isAddressFieldFailedPass = true
		} else {
			form.clearErrors(fieldName)
		}
	}

	if (isAddressFieldFailedPass) {
		return
	}

	if (form.password !== form.password_confirmation) {
		form.setError("password", "password not match")
		form.setError("password_confirmation", "Password does not match")
		return
	}

	const { isDirty, errors, __rememberable, hasErrors, progress, wasSuccessful, ...payload } = form

	if (isUserInputPassed(payload)) {
		return
	}

	isLoading.value = true
	form.clearErrors()

	try {
		await axios.post("/app/register-from-standalone", {
			...payload,
			tiktok_code: queryParam("tiktok_code"),
		})

		const gtmTagsFired = pushGtmEventAndWaitForTags("registrationSuccess", {
			user_data: buildRegistrationUserData(form, countriesAddressData.value),
		})

		const redirectUrl = await getRefRedirect({ registered: true })
		await gtmTagsFired

		window.location.href = redirectUrl
	} catch (error: any) {
		isLoading.value = false

		const responseErrors = error.response?.data?.errors
		if (responseErrors) {
			for (const key in responseErrors) {
				const message = responseErrors[key]
				form.setError(key, Array.isArray(message) ? message[0] : message)
			}
		} else if (error.response?.data?.message) {
			form.setError("email", error.response.data.message)
		}
	}
}
</script>

<template>
	<div
		:id="fieldValue?.id ? fieldValue?.id : 'register'"
		component="register"
		:style="getStyles(fieldValue?.container?.properties, screenType)">
		<div class="mx-auto w-full max-w-2xl">
			<div class="editor-class text-3xl font-semibold" v-html="fieldValue?.register?.title" />

			<div
				class="mt-6 overflow-hidden"
				:style="getStyles(fieldValue?.register?.card?.container?.properties, screenType)">
				<div class="editor-class" v-html="fieldValue?.register?.description" />

				<div v-if="isLoadingData" class="mt-8 space-y-6">
					<div v-for="index in 5" :key="index" class="animate-pulse space-y-2">
						<div class="h-3 w-32 rounded bg-gray-200" />
						<div class="h-10 w-full rounded bg-gray-200" />
					</div>
				</div>

				<form v-else class="mt-8" @submit.prevent="submit">
					<div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
						<div class="sm:col-span-6">
							<label for="email" class="block text-sm font-medium text-gray-700">
								<FontAwesomeIcon icon="fas fa-asterisk" class="text-xxs text-red-500" fixed-width aria-hidden="true" />
								{{ trans("Email") }}
								<FontAwesomeIcon
									v-tooltip="trans('Will be used as your username as well')"
									icon="fal fa-info-circle"
									class="text-gray-400 hover:text-gray-600"
									fixed-width
									aria-hidden="true" />
							</label>

							<div class="mt-2">
								<IconField class="w-full" :class="form.errors.email ? 'errorShake rounded-lg' : ''">
									<InputIcon>
										<FontAwesomeIcon :icon="faEnvelope" />
									</InputIcon>

									<InputText
										v-model="form.email"
										type="email"
										id="email"
										name="email"
										class="w-full"
										required
										@change="() => form.clearErrors('email')" />
								</IconField>

								<p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
									{{ form.errors.email }}
								</p>
							</div>
						</div>

						<div class="sm:col-span-3">
							<label for="password" class="block text-sm font-medium capitalize text-gray-700">
								<FontAwesomeIcon icon="fas fa-asterisk" class="text-xxs text-red-500" fixed-width aria-hidden="true" />
								{{ trans("Password") }}
							</label>
							<div class="password mt-2">
								<PureInput
									v-model="form.password"
									:type="'password'"
									:class="form.errors.password ? 'errorShake' : ''"
									required
									@update:modelValue="() => form.clearErrors('password')" />
								<p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
									{{ form.errors.password }}
								</p>
							</div>
						</div>

						<div class="sm:col-span-3">
							<label for="password-confirmation" class="block text-sm font-medium capitalize text-gray-700">
								<FontAwesomeIcon icon="fas fa-asterisk" class="text-xxs text-red-500" fixed-width aria-hidden="true" />
								{{ trans("Retype Password") }}
							</label>
							<div class="password mt-2">
								<PureInput
									v-model="form.password_confirmation"
									:type="'password'"
									:class="form.errors.password_confirmation ? 'errorShake' : ''"
									required />
								<p v-if="form.errors.password_confirmation" class="mt-1 text-sm text-red-600">
									{{ form.errors.password_confirmation }}
								</p>
							</div>
						</div>

						<FieldStandaloneRegistration
							:countriesAddressData="countriesAddressData"
							:polls="polls"
							:form="form"
							:requiresPhoneNumber="requiresPhoneNumber"
							:registration_settings="registrationSettings" />

						<div class="flex gap-2 sm:col-span-6">
							<Checkbox v-model="form.is_opt_in" inputId="opt_in_newsletter" name="opt_in_newsletter" binary class="mt-0.5" />
							<label for="opt_in_newsletter">
								{{ registrationSettings?.marketing_opt_in_label ?? trans("Opt in to our newsletter for updates and offers.") }}
							</label>
						</div>

						<div class="flex gap-2 sm:col-span-6" :class="isErrorTnc ? 'errorShake' : ''">
							<Checkbox
								v-model="isAgreeTnc"
								inputId="is_agree_tnc"
								name="is_agree_tnc"
								binary
								class="mt-0.5"
								@update:model-value="() => (isErrorTnc = false)" />
							<label for="is_agree_tnc">
								<a :href="fieldValue?.register?.terms?.url || '/terms-and-conditions'" target="_blank" class="underline">
									{{ fieldValue?.register?.terms?.text || trans("I agree with the terms and conditions") }}
								</a>
							</label>
						</div>
					</div>

					<div class="mt-10">
						<div
							v-if="registrationWarning.tax_number"
							class="mb-4 rounded border border-amber-300 bg-amber-100 px-4 py-2 text-amber-700">
							<span class="font-bold">{{ trans("Warning") }}:</span>
							<ul class="list-inside list-disc">
								<li v-if="Array.isArray(registrationWarning.tax_number)" v-for="(msg, i) in registrationWarning.tax_number" :key="i">
									{{ msg }}
								</li>
								<li v-else>
									{{ registrationWarning.tax_number }}
								</li>
							</ul>
						</div>

						<div
							v-if="Object.keys(form?.errors ?? {}).filter((key) => key !== 'tax_number').length"
							class="mb-4 text-red-600">
							<span class="font-bold">{{ trans("Errors") }}:</span>
							<ul class="list-inside list-disc">
								<template v-for="(error, key) in form.errors" :key="key">
									<template v-if="key !== 'tax_number'">
										<li v-if="Array.isArray(error)" v-for="(msg, i) in error" :key="i">{{ msg }}</li>
										<li v-else>{{ error }}</li>
									</template>
								</template>
							</ul>
						</div>

						<div class="relative flex justify-end">
							<button
								type="submit"
								:disabled="isLoading"
								class="inline-flex w-full cursor-pointer items-center justify-center gap-x-2 transition duration-75 ease-in-out disabled:opacity-70"
								:style="getStyles(fieldValue?.register?.button?.container?.properties, screenType)">
								{{ fieldValue?.register?.button?.text || trans("Register") }}
								<LoadingIcon v-if="isLoading" />
							</button>
							<div @click="() => (!isAgreeTnc ? (isErrorTnc = true) : submit())" class="absolute inset-0 cursor-pointer" />
						</div>
					</div>
				</form>
			</div>

			<div class="editor-class mt-4 text-sm" v-html="fieldValue?.register?.login?.note" />
		</div>

		<Modal :isOpen="isModalRemoveScript" width="w-full max-w-lg" @onClose="isModalRemoveScript = false">
			<div class="flex min-h-full items-end justify-center px-2 py-3 text-center sm:items-center">
				<div class="relative w-full transform overflow-hidden rounded-lg bg-white text-left transition-all">
					<div>
						<div class="mx-auto flex size-16 items-center justify-center rounded-full bg-gray-100">
							<FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-4xl text-red-500" fixed aria-hidden="true" />
						</div>

						<div class="mt-3 text-center">
							<div class="text-2xl font-semibold text-red-600">
								{{ trans("Don't do that to us") }}!
							</div>
							<div class="mt-2 text-sm opacity-75">
								{{ trans("Please remove the script before you submit") }}
							</div>
						</div>
					</div>

					<div class="mt-5 sm:mt-6">
						<Button :label="trans('Okay')" full @click="() => (isModalRemoveScript = false)" />
					</div>
				</div>
			</div>
		</Modal>

		<Modal :isOpen="isModalRemoveHtml" width="w-full max-w-2xl" @onClose="isModalRemoveHtml = false">
			<div class="flex min-h-full items-end justify-center px-2 py-3 text-center sm:items-center">
				<div class="relative w-full transform overflow-hidden rounded-lg bg-white text-left transition-all">
					<div>
						<div class="mx-auto flex size-16 items-center justify-center rounded-full bg-gray-100">
							<FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-4xl text-amber-500" fixed aria-hidden="true" />
						</div>

						<div class="mt-3 text-center">
							<div class="text-2xl font-semibold text-amber-600">
								{{ trans("Remove the HTML code") }}!
							</div>
							<div class="mt-2 text-sm opacity-75">
								{{ trans("It looks like you have added HTML code. Please remove the HTML code before you submit.") }}
							</div>
						</div>
					</div>

					<div class="mt-5 sm:mt-6">
						<Button :label="trans('Okay')" full @click="() => (isModalRemoveHtml = false)" />
					</div>
				</div>
			</div>
		</Modal>
	</div>
</template>

<style scoped lang="scss">
.password {
	.p-PureInputtext {
		width: 100% !important;
	}
}
</style>
