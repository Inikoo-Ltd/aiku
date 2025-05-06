<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'

import Modal from '@/Components/Utils/Modal.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faFile as falFile, faTimes } from '@fal'
import { faFileDownload, faDownload, faTimesCircle, faCheckCircle } from '@fas'
import { faEllipsisV } from '@far'
import { faInfoCircle } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import axios from 'axios'
import { Upload } from '@/types/Upload'


import { useFormatTime } from '@/Composables/useFormatTime'
import { UploadPallet } from '@/types/Pallet'
import { Link, router } from "@inertiajs/vue3"
import { useEchoGrpPersonal } from '@/Stores/echo-grp-personal'
import Papa from 'papaparse'
import * as XLSX from 'xlsx'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import LoadingIcon from './LoadingIcon.vue'
import { set } from 'lodash-es'
library.add(falFile, faEllipsisV, faTimes, faTimesCircle, faCheckCircle, faFileDownload, faDownload, faInfoCircle)


const props = defineProps<{
    scope?: string
    title?: {
        label?: string
        information?: string
    }
    additionalDataToSend?: string[]
    upload_spreadsheet?: Upload
    preview_template?: {
        unique_column: {
            [key: string]: {
                label: string
            }
        }
        header: string[]
        rows: {}[]
    }
    propsRefreshAfterFinish?: string[]
}>()

const model = defineModel()

const selectedEchopersonal = inject('selectedEchopersonal', {})

// const emits = defineEmits();

// const { isUploaded } = toRefs(props)

const isLoadingUpload = ref(false)
const dataHistoryFileUpload: any = ref([])
const isLoadingHistory = ref(false)
const isDraggedFile = ref(false)
const errorMessage = ref<string | null>(null)
const isIncludeStoreItems = ref(false)

const selectedFile = ref<File | null>(null)
const csvData = ref<string[]>([])

// Running when file is uploaded or dropped
const onUploadFile = async (fileUploaded: File) => {
    const fileExtension = fileUploaded?.name?.split('.').pop().toLowerCase();
    errorMessage.value = null;

    if (fileExtension === 'csv') {
        selectedFile.value = fileUploaded;
        Papa.parse(fileUploaded, {
            header: false,
            skipEmptyLines: true,
            complete: (results) => {
                csvData.value = results.data;
                // console.log('csvData', results.data);
            },
            error: (error) => {
                console.error('Error parsing CSV:', error);
            }
        });
    } else if (fileExtension === 'xlsx' || fileExtension === 'xls') {
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
        reader.readAsArrayBuffer(fileUploaded);
    } else {
        errorMessage.value = trans('File extension is not one of these:') + ' .csv, .xlsx, .xls';
    }
}

// Method: submit the selected file to server
const idRecentUpload = ref<number | null>(null)
const submitUpload = async () => {
    if (!props.upload_spreadsheet?.route?.upload?.name) {
        notify({
            title: 'Something went wrong.',
            text: 'Route is not set yet.',
            type: 'error',
        })
        return 
    }
    isDraggedFile.value = false
    errorMessage.value = null
    isLoadingUpload.value = true
    try {
        const aaa = await axios.post(
            route(props.upload_spreadsheet?.route?.upload?.name, props.upload_spreadsheet?.route?.upload?.parameters),
            {
                file: selectedFile.value,
                stored_item: isIncludeStoreItems.value
            },
            {
                headers: { "Content-Type": "multipart/form-data" },
                // onUploadProgress: function(progressEvent) {
                //     var percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
                //     console.log('percent', percentCompleted)
                // }
            }
        )
        
        selectedFile.value = null  // Clear the selected file
        csvData.value = []  // Clear the preview table
        
        idRecentUpload.value = aaa.data.id
        // selectedEchopersonal.isShowProgress = true
        set(selectedEchopersonal, 'isShowProgress', true)
        

    } catch (error: any) {
        console.error(error)
        errorMessage.value = error?.response?.data?.message
    }
    isLoadingUpload.value = false
}

// Method: refresh all like new open the modal
const clearAll = () => {
    selectedFile.value = null
    csvData.value = []
    errorMessage.value = null
}

const closeModal = () =>{
    // selectedEchopersonal.isShowProgress = false
    set(selectedEchopersonal, 'isShowProgress', false)
    model.value = false
}

const compIndexStoredItemInPreview = computed(() => {
    return csvData.value?.[0]?.indexOf("stored_items")
})

// Fetch data history when Modal is opened
watch(model, async (newVal) => {
    if (props.upload_spreadsheet?.route?.history?.name) {
        isLoadingHistory.value = true
        if(newVal && !dataHistoryFileUpload.value.length) {  // to prevent fetch every modal appear
            console.log(props.upload_spreadsheet?.route)
            try {
                const data = await axios.get(route(props.upload_spreadsheet?.route?.history?.name, props.upload_spreadsheet?.route?.history?.parameters))
                dataHistoryFileUpload.value = data.data.data
            } catch (error: any) {
                dataHistoryFileUpload.value = []
                console.error(error.message)
            }
        }
    } else {
        dataHistoryFileUpload.value = []
    }
    isLoadingHistory.value = false
})

// Watch the recently uploaded data, if complete then reload the table
watch(() => selectedEchopersonal?.recentlyUploaded?.find((upload: {id: number}) => upload.id == idRecentUpload.value), (newVal) => {
    console.log('newVal', newVal)

    if(newVal?.total && (newVal?.done == newVal?.total)) {
        console.log('done', newVal?.done)
        console.log('total', newVal?.total)

        if (newVal?.number_success > 0) {
            router.reload({
                only: props.propsRefreshAfterFinish ?? ['pallets','goods'],  // Only reload the props with dynamic name tabSlug (i.e props.showcase, props.menu)
                onSuccess: () => {
                    notify({
                        title: trans('Upload finish'),
                        text: trans('Data in table has reloaded.'),
                        type: 'success',
                    })
                    model.value = false                    
                },
                onError: (e) => {
                    // console.log('eeerr', e)
                }
            })
        } else {
            notify({
                title: trans('Upload finish'),
                text: trans('0 data added. See upload page for details.'),
                type: 'info',
            })
        }

        
    }
})


const compHistoryList = computed(() => {
    return [...dataHistoryFileUpload.value, ...selectedEchopersonal.recentlyUploaded]
})

const isLoadingVisitHistory = ref<string | null>(null)

</script>

<template>
    <Modal :isOpen="model" @onClose="() => closeModal()" :closeButton="true" width="w-[800px]">
        <!-- <pre>{{ selectedEchopersonal }}</pre> -->
        <div class="flex flex-col justify-between h-[500px] overflow-y-auto pb-4 px-3">
            <div>
                <!-- Title -->
                <div class="flex justify-center py-2 text-gray-600 mb-3">
                    <div class="w-full">
                        <div class="flex gap-x-0.5 justify-center items-center">
                            <span class="text-lg font-bold">{{ title?.label }}</span>
                            <VTooltip v-if="title?.information" class="w-fit">
                                <FontAwesomeIcon icon='fad fa-info-circle' size="xs" class='text-gray-500' fixed-width
                                    aria-hidden='true' />
                                <template #popper>
                                    <div class="min-w-20 w-fit max-w-52 text-xs">
                                        {{ title?.information }}
                                    </div>
                                </template>
                            </VTooltip>
                        </div>

                        <!-- Preview: excel -->
                        <div v-if="preview_template" class="pb-2 w-full overflow-x-auto">
                            <table class="mt-2 w-full border border-gray-300">
                                <thead class="">
                                    <tr class="bg-gray-200 rounded-t-xl">
                                        <th v-for="(header, index) in preview_template.header" :key="index"
                                            class="text-center whitespace-nowrap overflow-ellipsis px-6 font-normal border-r border-gray-300 last:border-0"
                                        >
                                            {{ header }}
                                            <FontAwesomeIcon
                                                v-if="preview_template?.unique_column && preview_template?.unique_column?.[header]?.label"
                                                v-tooltip="preview_template?.unique_column[header].label"
                                                icon='far fa-ellipsis-v'
                                                class='text-indigo-500'
                                                fixed-width
                                                aria-hidden='true'
                                            />
                                        </th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                    <tr v-for="(row, rowIndex) in preview_template.rows" class="hover:bg-gray-50 border-t first:border-gray-400 border-gray-300 font-semibold">
                                        <template v-for="(column, columnIndex) in row" key="cellIndex">
                                            <td class="px-2 border-r border-gray-300 last:border-0">{{ column }}</td>
                                        </template>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Button: download -->
                        <div class="flex mt-1" :class="preview_template ? 'justify-end' : 'justify-center'">
                            <a v-if="upload_spreadsheet?.route?.download?.name" :href="route(upload_spreadsheet?.route?.download?.name, upload_spreadsheet?.route?.download?.parameters)"
                                class="group text-xs text-gray-600 cursor-pointer px-2 -mr-1.5 w-fit" download>
                                <span class="text-xs text-gray-400 group-hover:text-gray-600">
                                    <FontAwesomeIcon icon='fas fa-file-download' class='text-gray-400 group-hover:text-gray-600' aria-hidden='true' />
                                    {{ upload_spreadsheet?.template?.label || trans(`Download template .xlsx`) }}
                                </span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Section: Upload box -->
                <div class="grid gap-x-3 px-1">
                    <div @drop="(e: any) => (e.preventDefault(), onUploadFile(e.dataTransfer.files[0]))" @dragover.prevent
                        @dragenter.prevent @dragleave.prevent
                        class="relative max-w-full flex items-center justify-center rounded-lg border border-dashed border-gray-700/25 px-6 py-3 "
                        :class="[
                            {'hover:bg-gray-100': !isLoadingUpload},
                            errorMessage ? 'errorShake' : ''
                        ]">
                        <!-- Section: Upload area -->
                        <div v-if="selectedFile" class="text-gray-500 flex flex-col items-center gap-y-2">
                            <div class="flex items-center gap-x-1">
                                <FontAwesomeIcon icon="fal fa-file" class="mx-auto h-5 w-5 text-gray-300"
                                    aria-hidden="true" />
                                {{ selectedFile?.name }}
                            </div>
                            <Button @click="() => clearAll()" label="Remove file" type="negative" size="s" />
                        </div>

                        <!-- Section: Upload (empty state) -->
                        <div v-else-if="!isLoadingUpload" class="">
                            <label for="fileInput"
                                class="absolute cursor-pointer rounded-md inset-0 focus-within:outline-none focus-within:ring-0 focus-within:ring-gray-400 focus-within:ring-offset-0">
                                <input type="file" name="file" id="fileInput" class="sr-only"
                                    @change="(e: any) => onUploadFile(e.target.files[0])" ref="fileInput"
                                    accept=".xlsx, .xls, .csv" />
                                <div v-if="isDraggedFile"
                                    class="text-2xl text-gray-500 h-full flex justify-center items-center">
                                    Drop your file here
                                </div>
                            </label>

                            <div v-if="!isDraggedFile" class="text-center text-gray-500">
                                <div class="flex justify-center text-sm font-medium leading-6 ">
                                    {{ trans("Upload file") }}
                                </div>
                                <div class="flex w-fit mx-auto text-xs leading-6 ">
                                    <p class="">{{ trans("Drag and drop, or browse your files") }} (.csv, .xlx, .xlsx)</p>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Loading state (if upload progress) -->
                        <!-- <div v-else class="text-center">
                            <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin h-8' aria-hidden='true' />
                            <p class="text-gray-500">Uploading..</p>
                        </div> -->

                    </div>

                    <div v-if="errorMessage" class="mt-1 text-red-500 text-xs italic">
                        *{{ errorMessage }}
                    </div>
                
                    <!-- Section: Excel preview -->
                    <Transition name="headlessui">
                        <div v-if="csvData?.length" class="text-xxs mt-3 max-w-3xl overflow-x-hidden">
                            <div class="text-sm py-1 flex justify-between">
                                <div>{{ trans('Preview your data') }}</div>
                                
                                <!-- Hide for now -->
                                <!-- <div v-if="additionalDataToSend?.includes('stored_items')" class="text-xxs flex items-center gap-x-1 text-gray-500 hover:text-gray-600 italic">
                                    <label for="include_stored_items" class="select-none cursor-pointer">Include stored items</label>
                                    <input v-model="isIncludeStoreItems" id="include_stored_items" type="checkbox"
                                        class="h-3.5 w-3.5 rounded-sm text-indigo-600 focus:ring-0 cursor-pointer" />
                                </div> -->
                            </div>

                            <div class="w-full border border-gray-300 rounded-md overflow-x-auto">
                                <table class="w-full">
                                    <thead class="">
                                        <tr class="rounded-t-xl">
                                            <template v-for="(header, index) in csvData[0]">
                                                <Transition name="slide-to-up">
                                                    <th v-if="index != compIndexStoredItemInPreview || isIncludeStoreItems" :key="index"
                                                        class="whitespace-nowrap overflow-ellipsis pl-3 pr-1"
                                                        :class="upload_spreadsheet?.required_fields?.length ? upload_spreadsheet?.required_fields.includes(header.trim().replace(/ /g,'_').toLowerCase()) ? 'bg-green-100' : 'bg-red-100 hover:bg-red-200' : 'bg-gray-100'"
                                                        v-tooltip="upload_spreadsheet?.required_fields?.includes(header.trim().replace(/ /g,'_').toLowerCase()) ? trans('Correct column') : trans('This column is not match, will not be processed.') + (upload_spreadsheet?.required_fields?.length > 0 ? (' Must be one of these:') + ' ' + upload_spreadsheet?.required_fields?.join(', ') : null)"
                                                    >
                                                        {{ header }} 
                                                        <FontAwesomeIcon v-if="upload_spreadsheet?.required_fields?.includes(header.trim().replace(/ /g,'_').toLowerCase())" icon='fas fa-check-circle' class='text-green-600' fixed-width aria-hidden='true' />
                                                        <FontAwesomeIcon v-else icon='fas fa-times-circle' class='text-red-500' fixed-width aria-hidden='true' />
                                                    </th>
                                                </Transition>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, rowIndex) in csvData.slice(1, 6)" class="border border-gray-300">
                                            <template v-for="(cell, cellIndex) in row">
                                                <Transition name="slide-to-up">
                                                    <td v-if="cellIndex != compIndexStoredItemInPreview || isIncludeStoreItems" :key="cellIndex" class="pl-3">{{ cell }}</td>
                                                </Transition>
                                            </template>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-if="csvData?.length > 6" class="py-1 text-center bg-gray-100">
                                    {{ trans('and') }} {{ csvData.length-6 }} {{ trans('more') }}
                                </div>
                            </div>
                            <div class="flex justify-end mt-3 w-full">
                                <Button @click="() => submitUpload()" label="Submit" size="s" full :loading="isLoadingUpload" />
                            </div>
                        </div>
                        <div v-else />
                    </Transition>

                </div>
            </div>

            <!-- Section: table history -->
            <div class="flex items-start gap-x-2 gap-y-2 flex-col mt-4">
                <span class="primaryLink  "> {{ trans('Previous uploads') }}  </span>
                <div v-if="!isLoadingHistory" class="flex flex-wrap gap-x-2 gap-y-2">
                    <template v-if="compHistoryList.length">
                        <TransitionGroup name="list" tag="div" class="flex flex-wrap gap-x-2 gap-y-2">
                            <component
                                :is="
                                    history?.show_route?.name
                                        ? Link
                                        : 'div'
                                "
                                v-for="(history, index) in compHistoryList"
                                :key="'list' + index"
                                :href="history?.show_route?.name
                                    ? route(history.show_route.name, history.show_route.parameters)
                                    : '#'
                                "
                                @start="() => isLoadingVisitHistory = history.id"
                                @finish="() => isLoadingVisitHistory = null"
                                class="relative isolate"
                            >
                                <LoadingIcon v-if="isLoadingVisitHistory ? (isLoadingVisitHistory == history.id || isLoadingVisitHistory == history.action_id) : false" class="absolute top-3 right-2 z-10" />
                                <div class="relative w-36 ring-1 ring-gray-300 rounded px-2 pt-2.5 pb-1 flex flex-col justify-start border-t-[3px] border-gray-500 "
                                    :class="!history.id ? 'bg-white' : 'bg-gray-100 hover:bg-gray-200 cursor-pointer'"
                                    v-tooltip="!history.id ? 'Recently uploaded' : ''"
                                >
                                    <p class="text-lg leading-none text-gray-700 font-semibold">
                                        {{ history.number_rows ?? history.total }} <span
                                            class="text-xs text-gray-500 font-normal">rows</span>
                                    </p>
                                    <div class="flex gap-x-2">
                                        <span class="text-lime-600 text-xxs">
                                            {{ history.number_success ?? history.data.number_success }} success,
                                        </span>
                                        <span class="text-red-500 text-xxs">
                                            {{ history.number_fails ?? history.data.number_fails }} fails
                                        </span>
                                    </div>
                                    <span class="text-gray-400 text-xxs mt-2">
                                        {{ useFormatTime(history.uploaded_at || history.start_at, { formatTime: 'hm'}) }}
                                    </span>
                                </div>
                            </component>
                        </TransitionGroup>
                    </template>
                    <div v-else class="text-gray-500 text-xs">
                        {{ trans("No previous uploads") }}
                    </div>
                </div>

                <div v-else class="flex flex-wrap gap-x-2 gap-y-2">
                    <div v-for="(history, index) in 4" :key="index" class="w-36 h-20 skeleton rounded" />
                </div>
            </div>
        </div>

    </Modal>
</template>
