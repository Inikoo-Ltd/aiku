<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { faImage } from "@far"

// Components
import Image from "../Image.vue"
import { Image as ImageTS } from "@/types/Image"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import LoadingIcon from "../Utils/LoadingIcon.vue"
import { GridProducts } from "@/Components/Product"

const props = defineProps<{
    currentTab: string
    imagesCategoryBox: {
        label: string
        type: string
        column_in_db: string
        url?: string
        images?: ImageTS
        information?: string
    }[]
    imagesUpdateRoute: routeType
    dataTable: {}
}>()

const selectedDragImage = ref<ImageTS | null>(null)
const loadingSubmit = ref<null | number | string>(null)
const isModalEditVideo = ref(false)
const selectedVideoToUpdate = ref<any>(null)

function onStartDrag(event: DragEvent, img: ImageTS) {
    selectedDragImage.value = img
    event.dataTransfer?.setData("application/json", JSON.stringify(img))
}

function onSubmitImage(dataRow: any, categoryBox: any) {
    categoryBox.imageUrl = dataRow?.image?.original

    router[props.imagesUpdateRoute.method || "patch"](
        route(props.imagesUpdateRoute.name, props.imagesUpdateRoute.parameters),
        { [categoryBox.column_in_db]: dataRow.id },
        {
            preserveScroll: true,
            preserveState: true,
            only: ["images_category_box"],
            onStart: () => (loadingSubmit.value = categoryBox.column_in_db),
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully set image for :category", {
                        category: categoryBox.label,
                    }),
                    type: "success",
                })
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set image"),
                    type: "error",
                })
            },
            onFinish: () => (loadingSubmit.value = null),
        }
    )
}

function onDropImage(event: DragEvent, categoryBox: any) {
    const dataRowImage = JSON.parse(
        event.dataTransfer?.getData("application/json") || "{}"
    )
    if (dataRowImage) {
        onSubmitImage(dataRowImage, categoryBox)
    }
}

function onSubmitVideoUrl() {
    router[props.imagesUpdateRoute.method || "patch"](
        route(props.imagesUpdateRoute.name, props.imagesUpdateRoute.parameters),
        { [selectedVideoToUpdate.value.column_in_db]: selectedVideoToUpdate.value?.url },
        {
            preserveScroll: true,
            preserveState: true,
            only: ["images_category_box"],
            onStart: () => (loadingSubmit.value = "video"),
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit the data"),
                    type: "success",
                })
                isModalEditVideo.value = false
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set video url"),
                    type: "error",
                })
            },
            onFinish: () => (loadingSubmit.value = null),
        }
    )
}

const activeCategory = ref(null);



console.log('upload Image', props)
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 px-10 py-4">
        <!-- Left: Category Drop Areas -->
        <div v-if="currentTab === 'images' && imagesCategoryBox?.length" class="rounded-xl bg-white p-5 lg:col-span-2">
            <h3 class="mb-4 text-base font-semibold text-gray-700">
                {{ trans("Trade Units Images / Videos") }}
            </h3>

            <ul class="grid grid-cols-2 sm:grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                <li v-for="categoryBox in imagesCategoryBox" :key="categoryBox.column_in_db"
                    class="flex flex-col overflow-hidden rounded-xl border bg-gray-50 transition-all duration-200"
                    :class="{
                        'border-blue-500 ring-2 ring-blue-300 bg-blue-50 shadow-md':
                            activeCategory === categoryBox.column_in_db,
                    }" @dragover.prevent @dragenter.prevent="activeCategory = categoryBox.column_in_db"
                    @dragleave="activeCategory = null" @drop.prevent="onDropImage($event, categoryBox)">
                    <!-- Label -->
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-100 border-b">
                        <span class="truncate text-sm font-medium text-gray-700" :title="categoryBox.label">
                            {{ categoryBox.label }}
                        </span>
                        <FontAwesomeIcon v-if="categoryBox.information" v-tooltip="categoryBox.information"
                            icon="fal fa-info-circle" class="text-gray-400 hover:text-gray-600" fixed-width />
                    </div>

                    <!-- Drop Zone -->
                    <div class="relative flex h-36 w-full items-center justify-center bg-gray-50">
                        <Image v-if="categoryBox.images" :src="categoryBox.images"
                            class="max-h-full max-w-full object-contain" />
                        <div v-else class="flex flex-col items-center justify-center text-gray-400">
                            <FontAwesomeIcon :icon="faImage" class="mb-1 text-2xl" />
                            <span class="text-[12px] font-medium">
                                {{ trans("Drop image here") }}
                            </span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Right: Products (draggable) -->
        <div class="rounded-xl bg-white p-5 lg:col-span-1 flex flex-col border border-gray-200 rounded-lg bg-white">
            <h3 class="mb-4 text-base font-semibold text-gray-700">
                {{ trans("Image List") }}
            </h3>

            <!-- Scrollable Product List -->
            <div class="flex-1 overflow-y-auto max-h-[600px] pr-1 ">
                <GridProducts :resource="dataTable" gridClass="grid-cols-1">
                    <template #card="{ item }">
                        <article
                            class="group flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white mb-2 p-3 shadow-sm transition hover:shadow-md hover:border-blue-400 cursor-move"
                            draggable="true" @dragstart="onStartDrag(item, $event)" @dragend="activeCategory = null">
                            <!-- Left: Image + Info -->
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <!-- Product Image -->
                                <div
                                    class="relative flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-md bg-gray-100 group-hover:bg-gray-50 transition">
                                    <Image v-if="item?.image" :src="item?.image"
                                        class="max-h-full max-w-full object-contain" />
                                    <div v-else class="text-gray-400">
                                        <FontAwesomeIcon icon="fal fa-image" class="text-base" />
                                    </div>
                                </div>

                                <!-- Product Info -->
                                <div class="flex-1 min-w-0">
                                    <p class="truncate max-w-[160px] text-sm font-medium text-gray-800"
                                        :title="item?.name">
                                        {{ item?.name || trans("Unnamed product") }}
                                    </p>
                                    <span class="truncate max-w-[160px] block text-[11px] text-gray-500 italic"
                                        :title="item?.code">
                                        {{ item?.size  }}
                                    </span>
                                </div>
                            </div>

                            <!-- Right: Delete Button -->
                            <button @click="onDelete(item)"
                                class="ml-2 flex-shrink-0 rounded-full p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 transition"
                                v-tooltip="trans('Delete')">
                                <FontAwesomeIcon icon="fal fa-trash-alt" class="text-sm text-red-400" />
                            </button>
                        </article>
                    </template>
                </GridProducts>
            </div>
        </div>

    </div>

</template>

<style scoped>
li {
    transition: all 0.25s ease-in-out;
}
</style>