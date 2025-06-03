<script setup lang="ts">
import { Link, router, useForm } from "@inertiajs/vue3"
import { ref, onMounted, nextTick, watch, computed, inject } from "vue"
import PureInput from "@/Components/Pure/PureInput.vue"
// import RetinaShowIris from "@/Layouts/RetinaShowIris.vue"
import { trans } from "laravel-vue-i18n"
import Multiselect from "@vueform/multiselect"
import Address from "@/Components/Forms/Fields/Address.vue"
import FulfilmentCustomer from "@/Pages/Grp/Org/Fulfilment/FulfilmentCustomer.vue"
import CustomerDataForm from "@/Components/CustomerDataForm.vue"
import Textarea from "primevue/textarea"
import Select from "primevue/select"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEnvelope } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBuilding, faGlobe, faPhone, faUser } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { GoogleLogin, decodeCredential  } from 'vue3-google-login'
import RetinaShowIris from "@/Layouts/RetinaShowIris.vue"
import ValidationErrors from "@/Components/ValidationErrors.vue"
import { notify } from "@kyvg/vue3-notification"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faEnvelope, faUser, faPhone, faBuilding, faGlobe)

// Set default layout
defineOptions({ layout: RetinaShowIris })

const props = defineProps<{
	countriesAddressData: [],
	polls: {
		id: string,
		type: "option" | "text",
		label: string,
		name: string,
	}[],
	registerRoute: {
		name: string,
		parameters: string,
	},
	google: {
        client_id: string
    }
	
}>()

const layout = inject('layout', retinaLayoutStructure)

// di <script setup lang="ts">
const initialPollReplies = props.polls.map((poll) => ({
	id: poll.id,
	type: poll.type,
	label: poll.label,
	answer: poll.type === "option" ? null : "",
}))

// Define form using Inertia's useForm
const form = useForm({
	contact_name: "",
	email: "",
	phone: "",
	company_name: "",
	website: "",
	password: "",
	password_confirmation: "",
	contact_address: {},
	poll_replies: initialPollReplies,
})

// Define reactive variables
const isLoading = ref(false)

const submitRegister = () => {
	isLoading.value = true

	if (form.password == form.password_confirmation) {
		form.post(route(props.registerRoute.name, props.registerRoute.parameters), {
			onError: () => {
				isLoading.value = false
			},
			onFinish: () => {
				/* form.reset(); */
			},
		})
	} else {
		form.setError("password", "password not match")
	}
}

const addressFieldData = {
	type: "address",
	label: "Address",
	value: {
		address_line_1: null,
		address_line_2: null,
		sorting_code: null,
		postal_code: null,
		locality: null,
		dependent_locality: null,
		administrative_area: null,
		country_code: null,
		country_id: 48,
	},
	options: props.countriesAddressData,
}

// Autofocus first PureInput on mount
onMounted(async () => {
	await nextTick()
	document.getElementById("contact_name")?.focus()
})

const simplePolls = computed(() =>
	props.polls.map(({ name, label, options }) => ({ name, label, options }))
)

const initialPolls: Record<string, string | null> = {}
simplePolls.value.forEach((poll) => {
	initialPolls[poll.name] = poll.options.length > 1 ? null : ""
})


const isPasswordSame = computed(() => {
	return form.password === form.password_confirmation
})

const isLoadingGoogle = ref(false)
const onCallbackGoogleLogin = (e) => {
    // console.log('xxxxxx Google login callback', e)
    const userData = decodeCredential(e.credential)
    // console.log("zzz Handle the userData", userData)

    // Section: Submit
    router.post(
        route('retina.login_google', {
            shop: layout.website?.id
        }),
        {
            google_credential: e.credential,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingGoogle.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully register"),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to login with Google. Please contact administrator."),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingGoogle.value = false
            },
        }
    )
}
</script>

<template>
	<div class="rounded-md flex items-center justify-center w-full px-4 py-4 lg:px-8">
        <div class="relative w-full max-w-lg bg-white border border-gray-200 rounded-md shadow-lg px-8 py-10">
            <div v-if="isLoadingGoogle" class="absolute inset-0 bg-black/50 text-white z-10 flex justify-center items-center">
                <LoadingIcon class="text-4xl" />
            </div>

            <form class="flex flex-col gap-y-6">
                <!-- Username Field -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">
                        {{ trans('Email') }}
                    </label>
                    <div class="mt-1">
                        <PureInput
							v-model="form.username"
							id="email"
							name="email"
                            :autofocus="true"
							autocomplete="email"
							required
							placeholder="example@email.com"
                            @keydown.enter="submitRegister"
						/>
                    </div>
                </div>

				<div class="sm:col-span-3">
					<label for="password" class="capitalize block text-sm font-medium text-gray-700" > Password</label >
					<div class="mt-2 password">
						<PureInput
							v-model="form.password"
							@update:modelValue="(e) => form.clearErrors('password')"
							:type="'password'"
							required
						/>
						<p v-if="form.errors.password" class="text-sm text-red-600 mt-1">
							{{ form.errors.password }}
						</p>
					</div>
				</div>

				<!-- Retype Password -->
				<div class="sm:col-span-3">
					<label for="password-confirmation" class="capitalize block text-sm font-medium text-gray-700" > Retype Password </label>
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

				<Transition name="slide-to-right">
					<div v-if="!isPasswordSame" class="-mt-4 text-red-500 italic">
						{{ trans("Password is not match") }}
					</div>
				</Transition>

                <ValidationErrors />

                <!-- Submit Button -->
                <div class="space-y-2">
                    <Button full @click.prevent="submitRegister" :loading="isLoading" label="Register" xtype="'tertiary'" xclass="'!bg-[#C1A027] !text-white'" />
                </div>

                <!-- Google Login -->
                <div class="mx-auto w-fit">
                    <div class="text-center mb-4 text-sm">
                        Or
                    </div>

                    <GoogleLogin
                        :clientId="google.client_id"
                        :callback="(e) => onCallbackGoogleLogin(e)"
                        :error="(e) => console.log('yyyyyy error', e)"
                    >
                    
                    </GoogleLogin>
                </div>

                <!-- Registration Link -->
                <div class="border-t border-gray-200 flex justify-center items-center mt-2 pt-4">
                    <p class="text-sm text-gray-500">
                        <span class="font-normal">{{ trans("Already have an account?") }}</span>
                        <Link :href="route('retina.login.show')"
                            class="  font-medium hover:underline transition duration-150 ease-in-out ml-1">
                            {{ trans("Login here") }}
                        </Link>
                    </p>
                </div>
            </form>
        </div>
    </div>
	
	<div v-if="false" class="max-w-2xl mx-auto my-8">
		<!-- Card container -->
		<div class="bg-white border border-gray-200 rounded-lg shadow-md overflow-hidden">
			<!-- Card header -->
			<div class="px-6 py-4 border-b border-gray-200">
				<h2 class="text-xl font-semibold text-gray-800">Registration form</h2>
			</div>
			<form @submit.prevent="submitRegister" class="space-y-12 px-14 py-10">
				<div class="text-xl font-semibold flex justify-center">
					{{ trans("Join Our Dropship – Register Now!") }}
				</div>
				<div class="border-b border-gray-900/10 pb-12">
					<div class="mt-10 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
						<!-- First Name -->
						<div class="sm:col-span-6">
							<label
								for="name"
								class="capitalize block text-sm font-medium text-gray-700"
								>{{ trans("Name") }}</label
							>
							<div class="mt-2">
								<IconField>
									<InputIcon>
										<FontAwesomeIcon :icon="faUser" />
									</InputIcon>

									<InputText
										v-model="form.contact_name"
										id="contact_name"
										name="contact_name"
										class="w-full"
										required />
								</IconField>
								<p
									v-if="form.errors.contact_name"
									class="text-sm text-red-600 mt-1">
									{{ form.errors.contact_name }}
								</p>
							</div>
						</div>

						<!-- Email -->
						<div class="sm:col-span-3">
							<label
								for="email"
								class="capitalize block text-sm font-medium text-gray-700"
								>{{ trans("Email") }}</label
							>
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
										required />
								</IconField>

								<p v-if="form.errors.email" class="text-sm text-red-600 mt-1">
									{{ form.errors.email }}
								</p>
							</div>
						</div>

						<div class="sm:col-span-3">
							<label
								for="phone-number"
								class="capitalize block text-sm font-medium text-gray-700"
								>{{ trans("Phone Number") }}</label
							>
							<div class="mt-2">
								<IconField class="w-full">
									<InputIcon>
										<FontAwesomeIcon :icon="faPhone" />
									</InputIcon>

									<InputText
										v-model="form.phone"
										type="text"
										id="phone-number"
										name="phone"
										class="w-full"
										required />
								</IconField>

								<p v-if="form.errors.phone" class="text-sm text-red-600 mt-1">
									{{ form.errors.phone }}
								</p>
							</div>
						</div>

						<!-- Business Name -->
						<div class="sm:col-span-6">
							<label
								for="business-name"
								class="capitalize block text-sm font-medium text-gray-700"
								>{{ trans("Business Name") }}</label
							>
							<div class="mt-2">
								<IconField class="w-full">
									<InputIcon>
										<FontAwesomeIcon :icon="faBuilding" />
									</InputIcon>

									<InputText
										v-model="form.company_name"
										type="text"
										id="business-name"
										name="company_name"
										class="w-full" />
								</IconField>

								<p
									v-if="form.errors.company_name"
									class="text-sm text-red-600 mt-1">
									{{ form.errors.company_name }}
								</p>
							</div>
						</div>

						<!-- Website -->
						<div class="sm:col-span-6">
							<label
								for="website"
								class="capitalize block text-sm font-medium text-gray-700"
								>{{ trans("Website") }}</label
							>
							<div class="mt-2">
								<IconField class="w-full">
									<InputIcon>
										<FontAwesomeIcon :icon="faGlobe" />
									</InputIcon>

									<InputText v-model="form.website" class="w-full" />
								</IconField>
								<p v-if="form.errors.website" class="text-sm text-red-600 mt-1">
									{{ form.errors.website }}
								</p>
							</div>
						</div>

						<div class="sm:col-span-6">
							<hr />
						</div>

						<div class="sm:col-span-6">
							<label
								for="website"
								class="capitalize block text-sm font-medium text-gray-700"
								>{{ trans("Country") }}</label
							>
							<Address
								v-model="form[contact_address]"
								fieldName="contact_address"
								:form="form"
								:options="{ countriesAddressData: countriesAddressData }"
								:fieldData="addressFieldData" />
						</div>

						<div class="sm:col-span-6">
							<hr />
						</div>

						<div
							v-for="(pollReply, idx) in form.poll_replies"
							:key="pollReply.id"
							class="sm:col-span-6">
							<label class="block text-sm font-medium text-gray-700 capitalize">
								{{ pollReply.label }}
							</label>

							<Select
								v-if="pollReply.type === 'option'"
								v-model="form.poll_replies[idx].answer"
								:options="props.polls[idx].options"
								optionLabel="label"
								optionValue="id"
								:placeholder="`Please Choose One`"
								class="mt-2 w-full" />

							<Textarea
								v-else
								v-model="form.poll_replies[idx].answer"
								rows="5"
								cols="30"
								placeholder="Your answer…"
								class="mt-2 w-full border rounded-md p-2" />

							<p
								v-if="form.errors[`poll_replies.${idx}`]"
								class="mt-1 text-sm text-red-600">
								{{ form.errors[`poll_replies.${idx}`] }}
							</p>
						</div>

						<div class="sm:col-span-6">
							<hr />
						</div>

						<!-- Password -->
						<div class="sm:col-span-3">
							<label
								for="password"
								class="capitalize block text-sm font-medium text-gray-700"
								>Password</label
							>
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
								>Retype Password</label
							>
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
					</div>
				</div>

				<!-- Submit Button -->
				<div class="flex justify-end">
					<button
						type="submit"
						class="inline-flex items-center px-6 bg-black py-3 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
						<span v-if="isLoading" class="loader mr-2"></span>
						{{ trans("Register") }}
					</button>
				</div>
			</form>
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
