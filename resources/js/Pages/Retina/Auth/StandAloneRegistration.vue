<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import { ref, onMounted, nextTick } from "vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import { trans } from "laravel-vue-i18n"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEnvelope } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBuilding, faGlobe, faPhone, faUser, faInfoCircle } from "@fal"
import { faAsterisk } from "@fas"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { Checkbox } from "primevue"
import FieldStandaloneRegistration from "./Field/FieldStandaloneRegistration.vue"

library.add(faEnvelope, faUser, faAsterisk, faInfoCircle, faPhone, faBuilding, faGlobe)

// Set default layout
// defineOptions({ layout: RetinaShowIris })
const props = defineProps<{
	countriesAddressData: [],
	polls: [],
	registerRoute: {
		name: string,
		parameters: string,
	},
	timeline: {}
	current_timeline: string
	client: {
		email: string
		contact_name: string

	}
}>()



const initialPollReplies = props.polls.map((poll) => ({
	id: poll.id,
	type: poll.type,
	label: poll.label,
	answer: poll.type === "option" ? null : "",
	is_required: poll.in_registration_required,
}))


const form = useForm({
	contact_name: props.client?.contact_name || "",
	email: props.client?.email || "",
	phone: "",
	company_name: "",
	contact_website: "",
	password: "",
	password_confirmation: "",
	contact_address: {},
	poll_replies: initialPollReplies,
	is_opt_in: false,
	interest: [],
})

// Define reactive variables
const isLoading = ref(false)

const submit = () => {
	isLoading.value = true

	const { isDirty, errors, __rememberable, hasErrors, progress, wasSuccessful, ...xxx } = form
	// console.log('fooooorm', xxx)
	if (form.password == form.password_confirmation) {
		form
		.transform((data) => ({
			...data,
			...xxx
		}))
		.post(route(props.registerRoute.name, props.registerRoute.parameters), {
			preserveScroll: true,
			onError: () => {
				isLoading.value = false
			},
			onFinish: () => {

			},
		})
	} else {
		form.setError("password", "password not match")
	}
}



// Autofocus first PureInput on mount
onMounted(async () => {
	await nextTick()
	document.getElementById("contact_name")?.focus()
})


</script>

<template>
	
	<div class="pt-8">


		<div class="max-w-2xl mx-auto my-8">
			
			<div class="text-4xl font-semibold flex justify-center mb-8">
				{{ trans("Register") }}
			</div>

			<!-- Card container -->
			<div class="bg-white border border-gray-200 rounded-lg shadow-md overflow-hidden">
				<!-- Card header -->
				<div class="px-6 py-4 border-b border-gray-200">
					<h2 class="text-lg">
						{{ trans("Fill the form to complete your registration") }}
					</h2>
				</div>
				
				<form @submit.prevent="submit" class="space-y-12 px-14 pb-10">
					<div class="border-b border-gray-900/10 pb-12">
						<div class="mt-10 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
							<!-- Email -->
							<div class="sm:col-span-6">
								<label
									for="email"
									class="block text-sm font-medium text-gray-700"
                                >
									<FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
									{{ trans("Email") }}
									<FontAwesomeIcon v-tooltip="trans('Will be used as your username as well')" icon="fal fa-info-circle" class="text-gray-400 hover:text-gray-600" fixed-width aria-hidden="true" />
								</label>

								<div class="mt-2">
									<!-- make IconField full-width -->
									<IconField class="w-full">
										<InputIcon>
											<FontAwesomeIcon :icon="faEnvelope" />
										</InputIcon>

										<!-- and make the input itself full-width -->
										<InputText
											v-model="form.email"
											type="email"
											id="email"
											name="email"
											class="w-full"
											xdisabled
											required />
									</IconField>

									<p v-if="form.errors.email" class="text-sm text-red-600 mt-1">
										{{ form.errors.email }}
									</p>
								</div>
							</div>

							<!-- Password -->
							<div class="sm:col-span-3">
								<label
									for="password"
									class="capitalize block text-sm font-medium text-gray-700"
								>
									<FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
									Password
									</label>
								<div class="mt-2 password">
									<PureInput
										v-model="form.password"
										@update:modelValue="(e) => form.clearErrors('password')"
										:type="'password'"
										required />
									<p v-if="form.errors.password" class="text-sm text-red-600 mt-1">
										{{ form.errors.password }}
									</p>
								</div>
							</div>
							
							<!-- Retype Password -->
							<div class="sm:col-span-3">
								<label
									for="password-confirmation"
									class="capitalize block text-sm font-medium text-gray-700"
								>
									<FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
									Retype Password
								</label>
								<div class="mt-2 password">
									<PureInput
										v-model="form.password_confirmation"
										:type="'password'"
										required />
									<p
										v-if="form.errors.password_confirmation"
										class="text-sm text-red-600 mt-1">
										{{ form.errors.password_confirmation }}
									</p>
								</div>
							</div>

							<FieldStandaloneRegistration
								:countriesAddressData
								:polls
								:form
							/>
							
							<!-- Opt in newsletter -->
							<div class="flex items-center gap-2 sm:col-span-6">
								<Checkbox v-model="form.is_opt_in" inputId="opt_in_newsletter" name="opt_in_newsletter" binary />
								<label for="opt_in_newsletter">
									{{ trans("Opt in to our newsletter for updates and offers.") }}
								</label>
							</div>
						</div>
					</div>

					<!-- Submit Button -->
					<div>
						<div v-if="Object.keys(form.errors || {}).length" class="mb-4 text-red-600">
							There is {{ Object.keys(form.errors || {}).length }} error(s) in the form. Please correct them before submitting.

						</div>
						<div class="flex justify-end">
							<button
								type="submit"
								:disabled="isLoading"
								class="w-full inline-flex justify-center items-center px-6 bg-black py-3 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
								<span v-if="isLoading" class="loader mr-2">
									<LoadingIcon />
								</span>
								{{ trans("Register") }}
							</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>

<style scoped lang="scss">
.password {
	.p-PureInputtext {
		width: 100% !important;
	}
}
</style>
