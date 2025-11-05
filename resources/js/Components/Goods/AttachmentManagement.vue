<script setup lang="ts">
import { ref, reactive, computed } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUnlink, faInfoCircle, faFile, faStarChristmas, faFileCheck, faFilePdf, faFileWord } from "@fal"
import Message from "primevue/message"
import ProgressBar from "primevue/progressbar"
import { routeType } from "@/types/route"
import { router } from "@inertiajs/vue3"
import { Link } from "@inertiajs/vue3"

const props = defineProps<{
    data: {
        editable?: boolean
        id: any
        bucket_attachments?: boolean
        attachment_category_box: routeType[]
        attachRoute: routeType
        detachRoute: routeType
        attachments: {
            label: string
            type: string
            scope: string
            url?: string
            attachments?: any
            information?: string
            id?: number
        }[]
    }
}>()

console.log("AttachmentManagement props:", props)

const editable = ref(props.data.editable ?? true)
const loadingSubmit = ref<string | null>(null)
const activeCategory = ref<string | null>(null)
const uploadProgress = reactive<Record<string, number>>({})

const notifySuccess = (msg: string) =>
    notify({ title: trans("Success"), text: msg, type: "success" })
const notifyError = (msg: string) =>
    notify({ title: trans("Error"), text: msg, type: "error" })

async function uploadFiles(files: FileList, categoryBox: Record<string, any>) {
    if (!files?.length) return
    const formData = new FormData()
    Array.from(files).forEach((file, index) => formData.append(`attachments[${index}]`, file))
    for (const key in categoryBox) {
        if (categoryBox.hasOwnProperty(key)) formData.append(key, categoryBox[key])
    }

    try {
        loadingSubmit.value = categoryBox.scope
        uploadProgress[categoryBox.scope] = 0

        await axios.post(
            route(props.data.attachRoute.name, props.data.attachRoute.parameters),
            formData,
            {
                headers: { "Content-Type": "multipart/form-data" },
                onUploadProgress: (event) => {
                    const percent = Math.round((event.loaded / (event.total || 1)) * 100)
                    uploadProgress[categoryBox.scope] = percent
                },
            }
        )

        notifySuccess(trans("File(s) uploaded successfully"))
        router.reload()
    } catch (err: any) {
        console.error(err)
        notifyError(err.response?.data?.message || trans("Failed to upload file(s)"))
    } finally {
        loadingSubmit.value = null
        uploadProgress[categoryBox.scope] = 0
    }
}

function onDropFile(event: DragEvent, categoryBox: any) {
    event.preventDefault()
    const files = event.dataTransfer?.files
    if (files?.length) uploadFiles(files, categoryBox)
    activeCategory.value = null
}

function onClickBox(categoryBox: any) {
    if (!editable.value) return
    const input = document.createElement("input")
    input.type = "file"
    input.multiple = true
    input.onchange = (e: any) => {
        const files = e.target.files
        if (files?.length) uploadFiles(files, categoryBox)
    }
    input.click()
}

async function onDeletefilesInBox(categoryBox: any) {
    const payload = { [categoryBox.scope]: null }

    try {
        loadingSubmit.value = categoryBox.scope

        await axios.delete(
            route(props.data.detachRoute.name, {
                ...props.data.detachRoute.parameters,
                attachment: categoryBox.attachment.id,
            }),
            payload
        )

        notifySuccess(trans("Attachment deleted successfully"))

        // Optional: refresh UI (depending on your Inertia setup)
        if (typeof router !== "undefined" && router.reload) {
            router.reload()
        }
    } catch (err: any) {
        console.error(err)
        notifyError(err.response?.data?.message || trans("Failed to delete attachment"))
    } finally {
        loadingSubmit.value = null
    }
}



const getIcon = (type: string) => {
    switch (type.toLowerCase()) {
        case "pdf":
            return faFilePdf
        case "doc":
        case "docx":
            return faFileWord
        default:
            return faFileCheck // or faFile for a more generic icon
    }
}



</script>

<template>
    <!-- <pre>{{ props.data.attachments }}</pre> -->
    <div v-if="!editable" class="px-10 pt-4">
        <Message severity="warn" closable>
            <template #icon>
                <FontAwesomeIcon :icon="faInfoCircle" />
            </template>
            <span class="ml-2">
                You can only view this attachment because of insufficient permissions.
            </span>
        </Message>
    </div>

    <div class="px-10">
        <div v-if="props.data.attachment_category_box?.private?.length" class="rounded-xl bg-white p-5 lg:col-span-2">
            <div class="text-base font-semibold text-gray-700">
                <h3 class="mb-1">
                    {{ trans("Attachment Private") }}
                </h3>
                <div class="border-b border-gray-300 h-1 mb-4"></div>
            </div>

            <TransitionGroup name="fade-move" tag="ul"
                class="grid grid-cols-2 sm:grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                <li v-for="categoryBox in props.data.attachment_category_box.private" :key="categoryBox.scope"
                    class="relative flex flex-col overflow-hidden rounded-xl border bg-gray-50 transition duration-300 ease-in-out"
                    :class="{
                        'border-blue-500 ring-2 ring-blue-300 bg-blue-50 shadow-md': activeCategory === categoryBox.scope,
                        'cursor-pointer': editable,
                        'cursor-not-allowed': !editable,
                    }" @dragover.prevent @dragenter.prevent="activeCategory = categoryBox.scope"
                    @dragleave="activeCategory = null" @drop="onDropFile($event, categoryBox)">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-100 border-b">
                        <span class="truncate text-sm font-medium text-gray-700" :title="categoryBox.label">
                            {{ categoryBox.label }}
                        </span>
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon v-if="categoryBox.information" v-tooltip="categoryBox.information"
                                icon="fal fa-info-circle" class="text-gray-400 hover:text-gray-600" fixed-width />
                            <FontAwesomeIcon v-if="categoryBox.attachment && editable" :icon="faUnlink"
                                @click.stop="() => onDeletefilesInBox(categoryBox)"
                                class="text-red-600 cursor-pointer text-xs" />
                        </div>
                    </div>

                    <!-- Drop Zone / Preview -->
                    <div
                        class="flex flex-col h-36 w-full transition bg-gray-50 hover:bg-gray-100 rounded-md overflow-hidden relative">
                        <template v-if="categoryBox.attachment">
                            <a :href="route(categoryBox.download_route.name, categoryBox.download_route.parameters)" target="_blank"
                                class="flex flex-col items-center justify-center h-full text-green-700 bg-green-50  shadow-inner transition hover:bg-green-100 hover:scale-[1.02] cursor-pointer p-3">
                                <FontAwesomeIcon :icon="getIcon(categoryBox.attachment.type)"
                                    class="mb-2 text-3xl text-green-500 animate-pulse" />
                                <span class="text-[13px] font-semibold text-green-700 text-center">
                                    {{ categoryBox.attachment.name || trans("Attachment uploaded") }}
                                </span>
                                <a class="text-[11px] text-green-500 mt-1"  :href="route(categoryBox.download_route.name, categoryBox.download_route.parameters)"
                                    v-if="categoryBox.download_route" target="_blank">
                                {{ trans("Click to view") }}
                                </a>
                            </a>
                        </template>

                        <template v-else>
                            <div @click="onClickBox(categoryBox)"
                                class="flex flex-col items-center justify-center text-gray-400 h-full bg-gray-50  rounded-md hover:bg-gray-100 transition cursor-pointer p-3">
                                <FontAwesomeIcon :icon="faFile" class="mb-2 text-2xl" />
                                <span class="text-[12px] font-medium">
                                    {{ trans("Drop or click to upload") }}
                                </span>
                            </div>
                        </template>


                        <!-- ProgressBar always at the bottom -->
                        <div v-if="uploadProgress[categoryBox.scope] > 0" class="absolute bottom-3 left-0 w-full p-3">
                            <ProgressBar :value="uploadProgress[categoryBox.scope]" showValue
                                class="h-2 rounded-b-md" />
                        </div>
                    </div>


                </li>
            </TransitionGroup>
        </div>
    </div>


    <div class="px-10">
        <div v-if="props.data.attachment_category_box?.public?.length" class="rounded-xl bg-white p-5 lg:col-span-2">
            <div class="text-base font-semibold text-gray-700">
                <h3 class="mb-1">
                    {{ trans("Attachment Public") }}
                </h3>
                <div class="border-b border-gray-300 h-1 mb-4"></div>
            </div>


            <TransitionGroup name="fade-move" tag="ul"
                class="grid grid-cols-2 sm:grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                <li v-for="categoryBox in props.data.attachment_category_box?.public" :key="categoryBox.scope"
                    class="relative flex flex-col overflow-hidden rounded-xl border bg-gray-50 transition duration-300 ease-in-out"
                    :class="{
                        'border-blue-500 ring-2 ring-blue-300 bg-blue-50 shadow-md': activeCategory === categoryBox.scope,
                        'cursor-pointer': editable,
                        'cursor-not-allowed': !editable,
                    }" @dragover.prevent @dragenter.prevent="activeCategory = categoryBox.scope"
                    @dragleave="activeCategory = null" @drop="onDropFile($event, categoryBox)">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-100 border-b">
                        <span class="truncate text-sm font-medium text-gray-700" :title="categoryBox.label">
                            {{ categoryBox.label }}
                        </span>
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon v-if="categoryBox.information" v-tooltip="categoryBox.information"
                                icon="fal fa-info-circle" class="text-gray-400 hover:text-gray-600" fixed-width />
                            <FontAwesomeIcon v-if="categoryBox.attachment && editable" :icon="faUnlink"
                                @click.stop="() => onDeletefilesInBox(categoryBox)"
                                class="text-red-600 cursor-pointer text-xs" />
                        </div>
                    </div>

                    <!-- Drop Zone / Preview -->
                    <div
                        class="flex flex-col h-36 w-full transition bg-gray-50 hover:bg-gray-100 rounded-md overflow-hidden relative">
                        <template v-if="categoryBox.attachment">
                            <a :href="route(categoryBox.download_route.name, categoryBox.download_route.parameters)" target="_blank"
                                class="flex flex-col items-center justify-center h-full text-green-700 bg-green-50  shadow-inner transition hover:bg-green-100 hover:scale-[1.02] cursor-pointer p-3">
                                <FontAwesomeIcon :icon="getIcon(categoryBox.attachment.type)"
                                    class="mb-2 text-3xl text-green-500 animate-pulse" />
                                <span class="text-[13px] font-semibold text-green-700 text-center">
                                    {{ categoryBox.attachment.name || trans("Attachment uploaded") }}
                                </span>
                                 <a class="text-[11px] text-green-500 mt-1" :href="route(categoryBox.download_route.name, categoryBox.download_route.parameters)"
                                    v-if="categoryBox.download_route" target="_blank" method="get">
                                {{ trans("Click to view") }}
                                </a>
                            </a>
                        </template>

                        <template v-else>
                            <div @click="onClickBox(categoryBox)"
                                class="flex flex-col items-center justify-center text-gray-400 h-full bg-gray-50  rounded-md hover:bg-gray-100 transition cursor-pointer p-3">
                                <FontAwesomeIcon :icon="faFile" class="mb-2 text-2xl" />
                                <span class="text-[12px] font-medium">
                                    {{ trans("Drop or click to upload") }}
                                </span>
                            </div>
                        </template>

                        <!-- ProgressBar always at the bottom -->
                        <div v-if="uploadProgress[categoryBox.scope] > 0" class="absolute bottom-3 left-0 w-full p-3">
                            <ProgressBar :value="uploadProgress[categoryBox.scope]" showValue
                                class="h-2 rounded-b-md" />
                        </div>
                    </div>
                </li>
            </TransitionGroup>
        </div>
    </div>
</template>

<style scoped>
.fade-move-enter-active,
.fade-move-leave-active {
    transition: all 0.25s ease;
}

.fade-move-enter-from,
.fade-move-leave-to {
    opacity: 0;
    transform: scale(0.95);
}

.fade-move-move {
    transition: transform 0.25s ease;
}
</style>
