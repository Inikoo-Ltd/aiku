<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faPencil, faUnlink, faUpload, faVideo } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Tag from "@/Components/Tag.vue"
import { capitalize } from "lodash"
// Types
import { Image as ImageTS } from "@/types/Image"
import { routeType } from "@/types/route"
import { faStar } from "@far"
import { faStarAndCrescent, faStarChristmas } from "@fas"

const props = defineProps<{
    data: {
        id: {}
        images: {}
        bucket_images?: boolean
        images_update_route: routeType
        upload_images_route: routeType
        delete_images_route: routeType
        images_category_box: {
            label: string
            type: string
            column_in_db: string
            url?: string
            images?: ImageTS
            information?: string
            id?: number
        }[]
    }
}>()

// State
const selectedDragImage = ref<ImageTS | null>(null)
const loadingSubmit = ref<null | number | string>(null)
const isModalEditVideo = ref(false)
const selectedVideoToUpdate = ref<any>(null)
const activeCategory = ref<string | null>(null)


/* ---------------------------
   Helpers
---------------------------- */
function notifySuccess(msg: string) {
    notify({ title: trans("Success"), text: msg, type: "success" })
}

function notifyError(msg: string) {
    notify({ title: trans("Error"), text: msg, type: "error" })
}

/* ---------------------------
   Image & Video Handlers
---------------------------- */
function onSubmitImage(payload: any, categoryBox: any) {
    router[props.data.images_update_route.method || "patch"](
        route(props.data.images_update_route.name, props.data.images_update_route.parameters),
        payload,
        {
            preserveScroll: true,
            preserveState: true,
            only: ["images_category_box"],
            onStart: () => (
                loadingSubmit.value = categoryBox.column_in_db
            ),
            onSuccess: () => {
                router.reload({ only: ["images"] })
                notifySuccess(
                    trans("Successfully set image for :category", { category: categoryBox.label })
                )
            },
            onError: (e) => {
                console.error(e)
                notifyError(trans("Failed to set image"))
            },
            onFinish: () => (loadingSubmit.value = null),
        }
    )
}

function normalizeVideoUrl(url: string): string {
    if (!url) return ""

    // YouTube: standardize to embed link
    const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/)
    if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`

    // Vimeo: convert to embed
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/)
    if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`

    // Default: return as-is
    return url
}

/**
 * Handle video URL submission
 */
function onSubmitVideoUrl() {
    const normalizedUrl = normalizeVideoUrl(selectedVideoToUpdate.value?.url || "")

    router["patch"](
        route(props.data.images_update_route.name, props.data.images_update_route.parameters),
        { video_url: normalizedUrl },
        {
            preserveScroll: true,
            preserveState: true,
            only: ["images_category_box"],
            onStart: () => (loadingSubmit.value = "video"),
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully saved the video URL"),
                    type: "success",
                })
                router.reload({ only: ["images"] })
                isModalEditVideo.value = false
            },
            onError: () => {
                notify({
                    title: trans("Error"),
                    text: trans("Failed to save video URL"),
                    type: "error",
                })
            },
            onFinish: () => (loadingSubmit.value = null),
        }
    )
}


/* ---------------------------
   Drag & Drop Handlers
---------------------------- */
function onDropImage(event: DragEvent, categoryBox: any) {
    const dataRowImage = JSON.parse(event.dataTransfer?.getData("application/json") || "{}");
    console.log("dataRowImage", dataRowImage);

    if (!dataRowImage?.id) {
        activeCategory.value = null;
        return;
    }

    let payload: Record<string, any> = {
        [categoryBox.column_in_db]: dataRowImage.id
    };

    // Case 1: No sub_scope → clear old category if dragging from another
    if (!dataRowImage.sub_scope && selectedDragImage.value?.id === dataRowImage.id) {
        if (selectedDragImage.value?.column_in_db) {
            payload[selectedDragImage.value.column_in_db] = null;
        }
    }

    // Case 2: Has sub_scope → clear category from found item
    if (dataRowImage.sub_scope) {
        const foundItem = props.data.images_category_box.find(item => item.id === dataRowImage.id);
        console.log("foundItem", foundItem);

        if (foundItem?.column_in_db) {
            payload[foundItem.column_in_db] = null;
        }
    }

    console.log("payload", payload);
    onSubmitImage(payload, categoryBox);

    activeCategory.value = null;
}


function onStartDrag(event: DragEvent, img: any, fromCategory?: any) {
    selectedDragImage.value = { ...img, fromCategory }
    event.dataTransfer?.setData("application/json", JSON.stringify(img))
        ; (event.target as HTMLElement).classList.add("dragging")
}

function onEndDrag(event: DragEvent) {
    ; (event.target as HTMLElement).classList.remove("dragging")
    activeCategory.value = null
}

/* ---------------------------
   File Upload / Delete
---------------------------- */
async function uploadFiles(files: FileList) {
    if (!files?.length) return

    const formData = new FormData()
    Array.from(files).forEach((file) => formData.append("images[]", file))

    try {
        loadingSubmit.value = "upload"

        await axios.post(
            route(props.data.upload_images_route.name, props.data.upload_images_route.parameters),
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
        )

        notifySuccess(trans("Image(s) uploaded successfully"))
        router.reload({ only: ["images"] })
    } catch (e) {
        console.error(e)
        notifyError(trans("Failed to upload image(s)"))
    } finally {
        loadingSubmit.value = null
    }
}

function onUploadFile(event: Event) {
    const input = event.target as HTMLInputElement
    if (!input.files?.length) return
    uploadFiles(input.files)
    input.value = ""
}

function onDropFile(event: DragEvent) {
    if (event.dataTransfer?.files?.length) uploadFiles(event.dataTransfer.files)
}

function onDeletefilesInBox(categoryBox: any) {
    let payload = { [categoryBox.column_in_db]: null }
    onSubmitImage(payload, categoryBox)
}

function onDeleteFilesInList(categoryBox: any) {
    router.delete(
        route(props.data.delete_images_route.name, {
            ...props.data.delete_images_route.parameters,
            media: categoryBox.id,
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ["images_category_box"],
            onStart: () => (loadingSubmit.value = categoryBox.column_in_db),
            onSuccess: () => notifySuccess(trans("File deleted successfully")),
            onError: () => notifyError(trans("Failed to delete file")),
            onFinish: () => (loadingSubmit.value = null),
        }
    )
}

console.log('dddd', props)
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 px-10 py-4">
        <!-- Left: Drop Areas -->
        <div v-if="props.data.images_category_box?.length" class="rounded-xl bg-white p-5 lg:col-span-2">
            <h3 class="mb-4 text-base font-semibold text-gray-700">
                {{ trans("Media") }}
                <FontAwesomeIcon v-if="data.bucket_images" :icon="faStarChristmas" class="text-yellow-400"
                    v-tooltip="'Use images bucket'" />
            </h3>

            <TransitionGroup name="fade-move" tag="ul"
                class="grid grid-cols-2 sm:grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                <li v-for="categoryBox in props.data.images_category_box" :key="categoryBox.column_in_db"
                    class="relative flex flex-col overflow-hidden rounded-xl border bg-gray-50 transition duration-300 ease-in-out"
                    :class="{
                        'border-blue-500 ring-2 ring-blue-300 bg-blue-50 shadow-md':
                            activeCategory === categoryBox.column_in_db,
                    }" v-bind="categoryBox.type === 'image'
                        ? {
                            onDragover: (e) => e.preventDefault(),
                            onDragenter: (e) => {
                                e.preventDefault()
                                activeCategory = categoryBox.column_in_db
                            },
                            onDragleave: () => (activeCategory = null),
                            onDrop: (e) => onDropImage(e, categoryBox),
                        }
                        : {}">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-100 border-b">
                        <span class="truncate text-sm font-medium text-gray-700" :title="categoryBox.label">
                            {{ categoryBox.label }}
                        </span>
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon v-if="categoryBox.information" v-tooltip="categoryBox.information"
                                icon="fal fa-info-circle" class="text-gray-400 hover:text-gray-600" fixed-width />
                            <FontAwesomeIcon v-if="categoryBox.type == 'video'" @click="() => {
                                selectedVideoToUpdate = { ...categoryBox }
                                isModalEditVideo = true
                            }" :icon="faPencil" class="text-gray-400 hover:text-gray-600" fixed-width />
                            <FontAwesomeIcon v-if="categoryBox.images || categoryBox.url" :icon="faUnlink"
                                @click="() => onDeletefilesInBox(categoryBox)"
                                class="text-gray-400 text-red-600 cursor-pointer text-xs" />
                        </div>
                    </div>

                    <!-- Drop Zone -->
                    <div v-if="categoryBox.type == 'image'"
                        class="relative flex h-36 w-full items-center justify-center bg-gray-50"
                        :draggable="!!categoryBox.images"
                        @dragstart="(e) => categoryBox.images && onStartDrag(e, categoryBox)" @dragend="onEndDrag">
                        <Image v-if="categoryBox.images" :src="categoryBox.images" :style="{ objectFit: 'contain' }" />
                        <div v-else class="flex flex-col items-center justify-center text-gray-400">
                            <FontAwesomeIcon :icon="faImage" class="mb-1 text-2xl" />
                            <span class="text-[12px] font-medium">{{ trans('Drop image here') }}</span>
                        </div>
                    </div>


                    <div v-if="categoryBox.type == 'video'"
                        class="relative flex h-36 w-full items-center justify-center bg-gray-50 cursor-pointer"
                        @click="() => { selectedVideoToUpdate = { ...categoryBox }; isModalEditVideo = true }">

                        <!-- Video preview -->
                        <div v-if="categoryBox.url" class="relative w-full h-full">
                            <iframe class="w-full h-full rounded-md pointer-events-none" :src="categoryBox.url"
                                frameborder="0" allowfullscreen></iframe>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center text-gray-400">
                            <FontAwesomeIcon :icon="faVideo" class="mb-1 text-2xl" />
                            <span class="text-[12px] font-medium">
                                {{ trans("Click to edit video here") }}
                            </span>
                        </div>

                        <!-- Drag overlay -->
                        <div v-show="activeCategory === categoryBox.column_in_db"
                            class="absolute inset-0 bg-blue-200 bg-opacity-30 border-2 border-dashed border-blue-500 rounded-md pointer-events-none">
                        </div>
                    </div>


                </li>
            </TransitionGroup>
        </div>

        <!-- Right: Image List -->
        <div
            class="lg:col-span-1 flex flex-col p-5 bg-white rounded-xl shadow-sm border h-fit max-h-[600px] overflow-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-semibold text-gray-700">
                    {{ trans("Image List") }}
                </h3>
                <div class="flex items-center gap-2">
                    <Button :loading="loadingSubmit === 'upload'" type="create" :label="trans('Upload')"
                        :icon="faUpload" @click="$refs.fileInput.click()" />
                    <input ref="fileInput" type="file" accept="image/*" multiple class="hidden"
                        @change="onUploadFile($event)" />
                </div>
            </div>

            <!-- Drop Zone -->
            <div class="relative flex-1 overflow-y-auto rounded-lg  transition scrollbar-thin scrollbar-thumb-gray-300 hover:scrollbar-thumb-gray-400"
                :class="isDragOver ? 'border-blue-400 bg-blue-50 shadow-md' : 'border-gray-200'"
                @dragover.prevent="isDragOver = true" @dragleave="isDragOver = false"
                @drop.prevent="onDropFile($event); isDragOver = false">
                <!-- Overlay saat drag -->
                <div v-if="isDragOver" class="absolute inset-0 z-10 flex flex-col items-center justify-center
             bg-blue-50/80 backdrop-blur-sm text-blue-500 pointer-events-none">
                    <FontAwesomeIcon :icon="faUpload" class="text-3xl mb-2" />
                    <p class="text-sm font-medium">{{ trans("Drop files to upload") }}</p>
                </div>

                <!-- Loader -->
                <div v-if="loadingSubmit === 'list'" class="flex justify-center p-6 text-gray-500">
                    <FontAwesomeIcon icon="fal fa-spinner-third" class="animate-spin mr-2" />
                    {{ trans("Loading images...") }}
                </div>

                <!-- List of images -->
                <div v-else>
                    <article v-for="item in props.data.images" :key="item.id" class="group flex items-center justify-between gap-3 p-1 bg-white mb-1 border
         hover:shadow-md hover:border-blue-400 transition" draggable="true" @dragstart="onStartDrag($event, item)"
                        @dragend="onEndDrag($event)">
                        <!-- Image + Info -->
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="relative flex h-14 w-14 flex-shrink-0 items-center justify-center
                overflow-hidden bg-gray-100 group-hover:bg-gray-50 transition">
                                <Image v-if="item?.image" :src="item?.image"
                                    class="max-h-full max-w-full object-contain" />
                                <div v-else class="text-gray-400">
                                    <FontAwesomeIcon :icon="faImage" class="text-base" />
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate max-w-[140px] text-sm font-medium text-gray-800"
                                        :title="item?.name">
                                        {{ item?.name || trans("Unnamed product") }}
                                    </p>

                                    <!-- Tag PrimeVue untuk sub_scope -->
                                    <Tag v-if="item?.sub_scope"
                                        :label="capitalize(item.sub_scope + (item.sub_scope != 'main' ? ' side' : ''))"
                                        :size="'xxs'" />
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="truncate max-w-[160px] block text-[11px] text-gray-500 italic"
                                        :title="item?.size">
                                        {{ item?.size }}
                                    </span>
                                    <span class="block font-medium text-[11px] text-gray-500 italic"
                                        :title="item?.size">
                                        <template v-if="item?.dimensions?.height && item?.dimensions?.width">
                                            {{ item.dimensions.height + 'x' + item.dimensions.width }}
                                        </template>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Delete -->
                        <button @click="onDeleteFilesInList(item)" class="ml-2 flex-shrink-0 rounded-full p-1.5 
           text-gray-400 hover:text-red-600 hover:bg-red-50 transition" v-tooltip="trans('Delete')">
                            <FontAwesomeIcon icon="fal fa-trash-alt" class="text-sm text-red-400" />
                        </button>
                    </article>

                </div>
            </div>
        </div>


    </div>


    <Dialog v-model:visible="isModalEditVideo" modal header="Edit Video Link" :style="{ width: '40rem' }">

        <div class="space-y-4">
            <!-- Input -->
            <InputText v-model="selectedVideoToUpdate.url" placeholder="https://youtube.com/..." class="w-full" />

            <!-- Video Preview -->
            <div v-if="selectedVideoToUpdate?.url" class="w-full aspect-video">
                <iframe class="w-full h-full rounded-md" :src="selectedVideoToUpdate.url" frameborder="0"
                    allowfullscreen></iframe>
            </div>
        </div>

        <!-- Footer -->
        <template #footer>
            <Button type="cancel" :label="trans('Cancel')" @click="isModalEditVideo = false" />
            <Button :loading="loadingSubmit === 'video'" type="create" :label="trans('Save')"
                @click="onSubmitVideoUrl()" />
        </template>
    </Dialog>

</template>

<style scoped>
/* TransitionGroup animations */
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

/* Smooth drag effect */
.dragging {
    opacity: 0.6;
    transform: scale(0.96);
}
</style>
