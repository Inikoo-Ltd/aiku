<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPencil, faUnlink, faUpload, faVideo, faInfoCircle } from "@fal"
import { Message } from "primevue"
// Types
import { attachment as attachmentTS } from "@/types/attachment"
import { routeType } from "@/types/route"
import { faFile, faStarChristmas } from "@fas"

const props = defineProps<{
    data: {
        editable?: boolean
        id: {}
        bucket_attachments?: boolean
        attachment_category_box: routeType
        attachRoute: routeType
        detachRoute: routeType
        attachments: {
            label: string
            type: string
            column_in_db: string
            url?: string
            attachments?: attachmentTS
            information?: string
            id?: number
        }[]
    }
}>()

// State
const editable = ref(props.data.editable ?? true)
const selectedDragattachment = ref<attachmentTS | null>(null)
const loadingSubmit = ref<null | number | string>(null)
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
   attachment & Video Handlers
---------------------------- */
function onSubmitAttachment(payload: any, categoryBox: any) {
}

/* ---------------------------
   Drag & Drop Handlers
---------------------------- */
function onDropAttachment(event: DragEvent, categoryBox: any) {
}


function onStartDrag(event: DragEvent, img: any, fromCategory?: any) {
    selectedDragattachment.value = { ...img, fromCategory }
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
    onSubmitAttachment(payload, categoryBox)
}

function onDeleteFilesInList(categoryBox: any) { }

</script>

<template>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 px-10 py-4">
        <!-- Left: Drop Areas -->
        <div v-if="props.data.attachment_category_box?.length" class="rounded-xl bg-white p-5 lg:col-span-2">
            <h3 class="mb-4 text-base font-semibold text-gray-700">
                {{ trans("Attachment") }}
                <FontAwesomeIcon v-if="data.bucket_attachments" :icon="faStarChristmas" class="text-yellow-400"
                    v-tooltip="'Use attachments bucket'" />
            </h3>

            <TransitionGroup name="fade-move" tag="ul"
                class="grid grid-cols-2 sm:grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                <li v-for="categoryBox in props.data.attachment_category_box" :key="categoryBox.column_in_db"
                    class="relative flex flex-col overflow-hidden rounded-xl border bg-gray-50 transition duration-300 ease-in-out"
                    :class="{
                        'border-blue-500 ring-2 ring-blue-300 bg-blue-50 shadow-md': activeCategory === categoryBox.column_in_db,
                        ' cursor-not-allowed': !editable
                    }" v-bind="editable
                        ? {
                            onDragover: (e) => e.preventDefault(),
                            onDragenter: (e) => {
                                e.preventDefault()
                                activeCategory = categoryBox.column_in_db
                            },
                            onDragleave: () => (activeCategory = null),
                            onDrop: (e) => onDropAttachment(e, categoryBox),
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
                            <FontAwesomeIcon v-if="(categoryBox.attachments || categoryBox.url) && editable"
                                :icon="faUnlink" @click="() => onDeletefilesInBox(categoryBox)"
                                class="text-gray-400 text-red-600 cursor-pointer text-xs" />
                        </div>
                    </div>

                    <!-- Drop Zone -->
                    <div class="relative flex h-36 w-full items-center justify-center bg-gray-50 transition"
                        :class="{ ' cursor-not-allowed': !editable }" :draggable="editable && !!categoryBox.attachments"
                        @dragstart="(e) => editable && categoryBox.attachments ? onStartDrag(e, categoryBox) : null"
                        @dragend="(e) => editable ? onEndDrag(e) : null">
                        <attachment v-if="categoryBox.attachments" :src="categoryBox.attachments"
                            :style="{ objectFit: 'contain' }" />
                        <div v-else class="flex flex-col items-center justify-center text-gray-400">
                            <FontAwesomeIcon :icon="faFile" class="mb-1 text-2xl" />
                            <span class="text-[12px] font-medium">{{ trans('Drop attachment here') }}</span>
                        </div>
                    </div>
                </li>
            </TransitionGroup>
        </div>

        <div
            class="lg:col-span-1 flex flex-col p-5 bg-white rounded-xl shadow-sm border h-fit max-h-[600px] overflow-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-semibold text-gray-700">
                    {{ trans("Attachments List") }}
                </h3>
                <div class="flex items-center gap-2">
                    <Button v-if="editable" :loading="loadingSubmit === 'upload'" type="create" :label="trans('Upload')"
                        :icon="faUpload" @click="$refs.fileInput.click()" />
                    <input ref="fileInput" type="file" accept="/*" multiple class="hidden"
                        @change="onUploadFile($event)" />
                </div>
            </div>

            <!-- Drop Zone -->
            <div class="relative flex-1 overflow-y-auto rounded-lg  transition scrollbar-thin scrollbar-thumb-gray-300 hover:scrollbar-thumb-gray-400"
                :class="isDragOver ? 'border-blue-400 bg-blue-50 shadow-md' : 'border-gray-200'"
                @dragover.prevent="isDragOver = true" @dragleave="isDragOver = false"
                @drop.prevent="onDropFile($event); isDragOver = false">
                <!-- Overlay  drag -->
                <div v-if="isDragOver && editable" class="absolute inset-0 z-10 flex flex-col items-center justify-center
             bg-blue-50/80 backdrop-blur-sm text-blue-500 pointer-events-none">
                    <FontAwesomeIcon :icon="faUpload" class="text-3xl mb-2" />
                    <p class="text-sm font-medium">{{ trans("Drop files to upload") }}</p>
                </div>

                <!-- Loader -->
                <div v-if="loadingSubmit === 'list'" class="flex justify-center p-6 text-gray-500">
                    <FontAwesomeIcon icon="fal fa-spinner-third" class="animate-spin mr-2" />
                    {{ trans("Loading attachment...") }}
                </div>

                <!-- List of images -->
                <div v-else>
                    <div v-if="!props.data.attachments || props.data.attachments.length === 0"
                        class="p-4 text-center text-sm text-gray-500 italic">
                        {{ trans("No attachments available") }}
                    </div>

                    <!-- if has gambar -->
                    <article v-else v-for="item in props.data.attachments" :key="item.id" class="group flex items-center justify-between gap-3 p-1 bg-white mb-1 border
              hover:shadow-md hover:border-blue-400 transition" :draggable="editable"
                        @dragstart="(e) => editable ? onStartDrag(e, item) : null"
                        @dragend="(e) => editable ? onEndDrag(e) : null">
                        <!-- Image + Info -->
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="relative flex h-14 w-14 flex-shrink-0 items-center justify-center
               overflow-hidden bg-gray-100 group-hover:bg-gray-50 transition">
                                <Image v-if="item?.image" :src="item?.image"
                                    class="max-h-full max-w-full object-contain" />
                                <div v-else class="text-gray-400">
                                    <FontAwesomeIcon :icon="faFile" class="text-base" />
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
                        <button v-if="editable" @click="onDeleteFilesInList(item)" class="ml-2 flex-shrink-0 rounded-full p-1.5 
             text-gray-400 hover:text-red-600 hover:bg-red-50 transition" v-tooltip="trans('Delete')">
                            <FontAwesomeIcon icon="fal fa-trash-alt" class="text-sm text-red-400" />
                        </button>
                    </article>
                </div>

            </div>
        </div>

    </div>

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
