<script setup lang="ts">
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle } from "@fas"
import { faCheck, faEnvelope } from "@fal"
import { set } from "lodash"
import { ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"

library.add(faCheck, faEnvelope, faCheckCircle)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()

const isLoadingSubmit = ref(false)
const currentState = ref("")
const inputEmail = ref("")
const errorMessage = ref("")
const onSubmitSubscribe = async () => {
	isLoadingSubmit.value = true
	errorMessage.value = ""
	currentState.value = ""

	try {
		await axios.post(
			window.origin + '/global/register-pre-customer/awf',
			{
				email: inputEmail.value,
				preview: true
			},
		)
		
		inputEmail.value = ""
		currentState.value = 'success'
	} catch (error) {
		console.error('Subscription failed', error)
		currentState.value = 'error'
		errorMessage.value = error?.email || 'An error occurred while subscribing.'
	}

	isLoadingSubmit.value = false
}
</script>

<template>
	<div
		class="flex flex-wrap justify-between"
		:style="getStyles(modelValue.container.properties, screenType)">
		<div class="mx-auto px-10 md:px-8 py-14">
			<div class="mt-0 xl:mt-0 w-fit mx-auto">
				<div class="text-sm/6 font-semibold text-center sm:text-2xl hover-text-input">
					<Editor
						:modelValue="modelValue.value.headline"
						@update:modelValue="(e) => {
							set(modelValue, ['value', 'headline'], e)
							emits('autoSave')
						}"
						class="hover-text-input"
						:toogle="[
							'heading', 'fontSize', 'bold', 'italic', 'underline', 'query', 'fontFamily',
							'blockquote', 'divider', 'alignLeft', 'alignRight', 'customLink',
							'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
						]"
					/>
				</div>

				<div class="mt-2 text-sm/6 text-center max-w-3xl">
					<Editor
						:modelValue="modelValue.value.description"
						@update:modelValue="(e) => {
							set(modelValue, ['value', 'description'], e)
							emits('autoSave')
						}"
						class="hover-text-input"
						:toogle="[
							'heading', 'fontSize', 'bold', 'italic', 'underline', 'query', 'fontFamily',
							'blockquote', 'divider', 'alignLeft', 'alignRight', 'customLink',
							'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
						]"
					/>
				</div>

				<Transition>
					<div v-if="currentState != 'success'" class="flex flex-col items-start">
						<form @submit.prevent="(e) => onSubmitSubscribe()" class="mt-6 sm:flex sm:justify-center sm:max-w-lg sm:items-center sm:w-full mx-auto">
							<label for="email-address" class="sr-only">
								Email address
							</label>

							<div class="relative w-full">
								<input
									type="email"
									v-model="inputEmail"
									name="email-address"
									id="email-address"
									autocomplete="email"
									required
									class="pl-9 text-gray-700 flex-1 w-full min-w-0 rounded-md bg-white px-3 py-1.5 text-base  outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6"
									:placeholder="modelValue?.value?.input?.placeholder"
									:disabled="isLoadingSubmit"
								/>
								<FontAwesomeIcon icon="fal fa-envelope" class="text-lg absolute text-gray-400 left-2.5 top-1/2 -translate-y-1/2" fixed-width aria-hidden="true" />
							</div>
							<div class="mt-4 sm:ml-4 sm:mt-0 sm:shrink-0">
								<button type="submit"
									XXclass="flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
									class="relative rounded-lg w-full transition-all"
									:style="getStyles(modelValue?.button?.container?.properties, screenType)"
								>
									<Transition name="spin-to-right">
										<LoadingIcon v-if="isLoadingSubmit" class="mr-2" />
									</Transition>
									{{ modelValue?.button?.text }}
								</button>
							</div>
						</form>

						<div v-if="currentState === 'error'" class="text-red-500 mt-2 italic">
							*{{ errorMessage }}
						</div>
					</div>

					<div v-else class="mx-auto mt-6 text-center text-green-500 flex flex-col items-center gap-y-2">
						<FontAwesomeIcon icon="fas fa-check-circle" class="text-4xl" fixed-width aria-hidden="true" />
						{{ trans("You have successfully subscribed") }}!
					</div>
				</Transition>
			</div>
		</div>
	</div>
</template>
