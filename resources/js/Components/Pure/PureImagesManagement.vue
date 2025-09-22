<script setup lang="ts">
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faPencil, faUnlink, faUpload, faVideo, faInfoCircle } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import { Image as ImageTS } from "@/types/Image"

const props = defineProps<{
    modelValue: {
        label: string
        type: string
        column_in_db: string
        url?: string
        images?: ImageTS | { preview: string }
        information?: string
        id?: number
        name?: string
        size?: string
        sub_scope?: string
        image?: string
        dimensions?: { width: number; height: number }
    }[]
}>()

const emit = defineEmits<{
    (e: "update:image", payload: { column: string; imageId: number | null }): void
    (e: "delete:image", payload: { id: number }): void
    (e: "upload:files", files: FileList, column?: string): void
    (e: "update:video", payload: { column: string; url: string }): void
}>()

// State
const selectedDragImage = ref<ImageTS | null>(null)
const loadingSubmit = ref<null | number | string>(null)
const isModalEditVideo = ref(false)
const selectedVideoToUpdate = ref<any>(null)
const activeCategory = ref<string | null>(null)

/* ---------------------------
   Drag & Drop Handlers
---------------------------- */
function onDropImage(event: DragEvent, categoryBox: any) {
    const dataRowImage = JSON.parse(event.dataTransfer?.getData("application/json") || "{}")
    if (!dataRowImage?.id) {
        activeCategory.value = null
        return
    }
    emit("update:image", { column: categoryBox.column_in_db, imageId: dataRowImage.id })
    activeCategory.value = null
}

function onStartDrag(event: DragEvent, img: any, fromCategory?: any) {
    selectedDragImage.value = { ...img, fromCategory }
    event.dataTransfer?.setData("application/json", JSON.stringify(img))
    ;(event.target as HTMLElement).classList.add("dragging")
}

function onEndDrag(event: DragEvent) {
    ;(event.target as HTMLElement).classList.remove("dragging")
    activeCategory.value = null
}

/* ---------------------------
   File Upload / Delete
---------------------------- */
function onFileSelected(event: Event, categoryBox: any) {
    const input = event.target as HTMLInputElement
    if (!input.files?.length) return
    const file = input.files[0]

    // Local preview
    const previewUrl = URL.createObjectURL(file)
    categoryBox.images = { preview: previewUrl } as any

    // Emit to parent
    emit("upload:files", input.files, categoryBox.column_in_db)

    input.value = "" // reset
}

function onDeletefilesInBox(categoryBox: any) {
    emit("update:image", { column: categoryBox.column_in_db, imageId: null })
}

/* ---------------------------
   Video
---------------------------- */
function normalizeVideoUrl(url: string): string {
    if (!url) return ""
    const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/)
    if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/)
    if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`
    return url
}

function onSubmitVideoUrl() {
    const normalizedUrl = normalizeVideoUrl(selectedVideoToUpdate.value?.url || "")
    emit("update:video", {
        column: selectedVideoToUpdate.value?.column_in_db,
        url: normalizedUrl,
    })
    isModalEditVideo.value = false
}
</script>

<template>
    <div class="grid gap-5 py-4" :class="bucket_images ? 'grid-cols-1 lg:grid-cols-3' : 'grid-cols-1 lg:grid-cols-2'">
        <!-- Left: Drop Areas -->
        <div v-if="props.modelValue.length" class="rounded-xl bg-white lg:col-span-2">
            <h3 class="mb-4 text-base font-semibold text-gray-700">
                <FontAwesomeIcon v-if="props.bucket_images" :icon="faInfoCircle" class="text-yellow-400"
                    v-tooltip="'Use images bucket'" />
            </h3>

            <TransitionGroup name="fade-move" tag="ul" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <li v-for="categoryBox in props.modelValue" :key="categoryBox.column_in_db"
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
                        : {}
                        ">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-100 border-b">
                        <span class="truncate text-sm font-medium text-gray-700" :title="categoryBox.label">
                            {{ categoryBox.label }}
                        </span>
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon v-if="categoryBox.information" v-tooltip="categoryBox.information"
                                icon="fal fa-info-circle" class="text-gray-400 hover:text-gray-600" fixed-width />
                            <FontAwesomeIcon v-if="categoryBox.type == 'video'" @click="
                                () => {
                                    selectedVideoToUpdate = { ...categoryBox }
                                    isModalEditVideo = true
                                }
                            " :icon="faPencil" class="text-gray-400 hover:text-gray-600" fixed-width />
                            <FontAwesomeIcon v-if="categoryBox.images || categoryBox.url" :icon="faUnlink"
                                @click="() => onDeletefilesInBox(categoryBox)"
                                class="text-gray-400 text-red-600 cursor-pointer text-xs" />
                        </div>
                    </div>

                    <!-- Drop Zone for Images -->
                    <div v-if="categoryBox.type == 'image'"
                        class="relative flex h-36 w-full items-center justify-center bg-gray-50 group"
                        :draggable="!!categoryBox.images"
                        @dragstart="(e) => categoryBox.images && onStartDrag(e, categoryBox)"
                        @dragend="onEndDrag">

                        <!-- Preview -->
                        <template v-if="categoryBox.images">
                            <!-- Local preview from file input -->
                            <img v-if="categoryBox.images.preview"
                                 :src="categoryBox.images.preview"
                                 class="w-full h-full object-contain" />

                            <!-- From backend / API -->
                            <Image v-else
                                   :src="categoryBox.images"
                                   :style="{ objectFit: 'contain' }"
                                   class="w-full h-full" />
                        </template>

                        <!-- Empty state -->
                        <div v-else class="flex flex-col items-center justify-center text-gray-400 cursor-pointer"
                            @click="$refs[`fileInput-${categoryBox.column_in_db}`][0].click()">
                            <FontAwesomeIcon :icon="faUpload" class="mb-1 text-2xl" />
                            <span class="text-[12px] font-medium">{{ trans("Click or drop image") }}</span>
                        </div>

                        <!-- Upload button overlay -->
                        <button type="button"
                            class="absolute bottom-2 right-2 bg-white border rounded-full p-2 text-gray-500 shadow group-hover:opacity-100 opacity-0 transition"
                            @click="$refs[`fileInput-${categoryBox.column_in_db}`][0].click()">
                            <FontAwesomeIcon :icon="faUpload" />
                        </button>

                        <!-- Hidden input -->
                        <input type="file" accept="image/*" class="hidden"
                            :ref="`fileInput-${categoryBox.column_in_db}`"
                            @change="(e) => onFileSelected(e, categoryBox)" />
                    </div>

                    <!-- Drop Zone for Videos -->
                    <div v-if="categoryBox.type == 'video'"
                        class="relative flex h-36 w-full items-center justify-center bg-gray-50 cursor-pointer" @click="
                            () => {
                                selectedVideoToUpdate = { ...categoryBox }
                                isModalEditVideo = true
                            }
                        ">
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
                    </div>
                </li>
            </TransitionGroup>
        </div>
    </div>

    <!-- Video Modal -->
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

.dragging {
    opacity: 0.6;
    transform: scale(0.96);
}
</style>
