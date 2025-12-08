<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"

import Modal from "@/Components/Utils/Modal.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFile as falFile, faTimes } from "@fal"
import { faFileDownload, faDownload, faTimesCircle, faCheckCircle } from "@fas"
import { faInfoCircle } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import axios from "axios"
import { Upload } from "@/types/Upload"

import { useFormatTime } from "@/Composables/useFormatTime"
import { UploadPallet } from "@/types/Pallet"
import { Link, router } from "@inertiajs/vue3"
import { useEchoGrpPersonal } from "@/Stores/echo-grp-personal"
import Papa from "papaparse"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import Select from "primevue/select"

library.add(
	falFile,
	faTimes,
	faTimesCircle,
	faCheckCircle,
	faFileDownload,
	faDownload,
	faInfoCircle
)

const props = defineProps<{
	scope?: string
	title?: {
		label?: string
		information?: string
	}
	additionalDataToSend?: string[]
	attachmentRoutes?: object
	options?: {
		name: string
		code: string
	}[]
}>()

const model = defineModel()

// const emits = defineEmits();

// const { isUploaded } = toRefs(props)

const isLoadingUpload = ref(false)
const dataHistoryFileUpload: any = ref([])
const isLoadingHistory = ref(false)
const isDraggedFile = ref(false)
const errorMessage = ref<string | null>(null)
const selectedType = ref()
const selectedFile = ref<File | null>(null)

// Running when file is uploaded or dropped
const onUploadFile = async (fileUploaded: File) => {
	const fileExtention = fileUploaded?.name?.split(".")?.pop()?.toLowerCase()
	errorMessage.value = null

	if (fileExtention) {
		selectedFile.value = fileUploaded;
        const reader = new FileReader();
        reader.onload = (e) => {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const sheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[sheetName];
            const json = XLSX.utils.sheet_to_json(worksheet, {header: 1, raw: false});
            csvData.value = json;
            // console.log('xlsx', json);
        };
        reader.onerror = (error) => {
            console.error('Error reading Excel file:', error);
        };
        reader.readAsArrayBuffer(fileUploaded)
	} else {
		errorMessage.value = trans("File extension is not one of these:")
	}
}

// Method: submit the selected file to server
const submitUpload = async () => {

	if (!props.attachmentRoutes?.importRoute?.name) {
		notify({
			title: "Something went wrong.",
			text: "Route is not set yet.",
			type: "error",
		})
		return
	}

	isDraggedFile.value = false
	errorMessage.value = null
	isLoadingUpload.value = true
	try {
		await axios.post(
			route(
				props.attachmentRoutes?.importRoute?.name,
				props.attachmentRoutes?.importRoute?.parameters
			),
			{
				file: selectedFile.value,
			},
			{
				headers: { "Content-Type": "multipart/form-data" },
			}
		)
		notify({
			title: "Success!",
			text: "The upload has successfully",
			type: "success",
		})
		router.reload({
			// onSuccess: () => {
			// 	console.log("reload")
			// },
			// onError: () => {
			// 	console.log("errorrrrrrrrr")
			// }
		})  // To reload the table to show new data
		closeModal()
		// useEchoGrpPersonal().isShowProgress = true
	} catch (error: any) {
		console.error(error)
		errorMessage.value = error?.response?.data?.message
	}
	isLoadingUpload.value = false
}

// Method: refresh all like new open the modal
const clearAll = () => {
	selectedFile.value = null
	errorMessage.value = null
}

const closeModal = () => {
	/*    useEchoGrpPersonal().isShowProgress = false */
	useEchoGrpPersonal().isShowProgress = false
	model.value = false
	console.log("model")
}
</script>

<template>
	<Modal :isOpen="model" @onClose="() => closeModal()" :closeButton="true" width="w-[500px]">
		<div class="flex flex-col justify-between pb-4 px-3">
			<div>
				<!-- Title -->
				<div class="flex justify-center py-2 text-gray-600 font-medium mb-3">
					<div>
						<div class="flex gap-x-0.5">
							{{ title?.label }}
						</div>
					</div>
				</div>

				<!-- Section: Upload box -->
				<div class="grid gap-x-3 px-1">

					<div
						@drop="(e: any) => (e.preventDefault(), onUploadFile(e.dataTransfer.files[0]))"
						@dragover.prevent
						@dragenter.prevent
						@dragleave.prevent
						class="overflow-hidden relative w-full max-w-full flex items-center justify-center rounded-lg border border-dashed border-gray-700/25 px-6 py-3 bg-gray-400/10"
						:class="[
							{ 'hover:bg-gray-400/20': !isLoadingUpload },
							errorMessage ? 'errorShake' : '',
						]">
						<!-- Section: Upload area -->
						<div
							v-if="selectedFile"
							class="text-gray-500 flex flex-col items-center gap-y-2 w-full">
							<div class="max-w-lg w-full text-center mx-auto overflow-hidden text-ellipsis">
								<FontAwesomeIcon
									icon="fal fa-file"
									class="h-5 w-5 text-gray-300"
									aria-hidden="true" />
								{{ selectedFile?.name }}
							</div>
							<Button
								@click="() => clearAll()"
								label="Remove file"
								type="negative"
								size="s" />
						</div>

						<!-- Section: Upload (empty state) -->
						<div v-else-if="!isLoadingUpload" class="">
							<label
								for="fileInput"
								class="absolute cursor-pointer rounded-md inset-0 focus-within:outline-none focus-within:ring-0 focus-within:ring-gray-400 focus-within:ring-offset-0">
								<input
									type="file"
									name="file"
									id="fileInput"

									class="sr-only"
									@change="(e: any) => onUploadFile(e.target.files[0])"
									ref="fileInput" />
								<div
									v-if="isDraggedFile"
									class="text-2xl text-gray-500 h-full flex justify-center items-center">
									{{ trans("Drop your file here") }}
								</div>
							</label>

							<div v-if="!isDraggedFile" class="text-center text-gray-500">
								<div class="flex justify-center text-sm font-medium leading-6">
									{{ trans("Upload file") }}
								</div>
								<div class="flex w-fit mx-auto text-xs leading-6">
									<p class="">
										{{ trans("Drag and drop, or browse your files") }}
									</p>
								</div>
							</div>
						</div>
					</div>

					<div v-if="errorMessage" class="mt-1 text-red-500 text-xs italic">
						*{{ errorMessage }}
					</div>

					<!-- Section: Attachment preview -->
					<!-- <Transition name="headlessui"> -->
						<!-- <div v-if="selectedFile" class="text-xxs mt-3 max-w-3xl overflow-x-hidden"> -->
							<div class="flex justify-end mt-4">
								<Button
									@click="() => submitUpload()"
									label="Submit"
									size="l"
									full

									:loading="isLoadingUpload" />
							</div>
						<!-- </div> -->
						<!-- <div v-else /> -->
					<!-- </Transition> -->
				</div>
			</div>
		</div>
	</Modal>
</template>

<style scoped>
/* Adjust the selector based on the rendered DOM of PrimeVue's Select.
   This example assumes the arrow is contained in an element with the class `.p-dropdown-trigger` */
.no-arrow .p-dropdown-trigger {
  display: none;
}
</style>
