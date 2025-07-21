<script setup lang="ts">
import { routeType } from '@/types/route'
import { ref } from 'vue'
import { router } from "@inertiajs/vue3"
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import Image from '../Image.vue'
import { Image as ImageTS } from '@/types/Image'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Table from '../Table/Table.vue'
import { Popover } from 'primevue'
import Button from '../Elements/Buttons/Button.vue'
import Modal from '../Utils/Modal.vue'
import PureInput from '../Pure/PureInput.vue'
import { set } from 'lodash-es'
import LoadingIcon from '../Utils/LoadingIcon.vue'

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
function onStartDrag(event: DragEvent, img: ImageTS) {
    selectedDragImage.value = img
    const data = JSON.stringify(img)
    console.log('Dropping data:', data)
    event.dataTransfer?.setData('application/json', data)
}

const loadingSubmit = ref<null | number | string>(null)

const onSubmitImage = (dataRow, category_box) => {
    console.log('Files dropped:', dataRow)
    category_box.imageUrl = dataRow?.image?.original
    router[props.imagesUpdateRoute.method || 'patch'](
        route(props.imagesUpdateRoute.name, props.imagesUpdateRoute.parameters),
        {
            [category_box.column_in_db]: dataRow.id,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['images_category_box'],
            onStart: () => { 
                loadingSubmit.value = category_box.column_in_db
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully set image for :category", { category: category_box.label }),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set image"),
                    type: "error"
                })
            },
            onFinish: () => {
                loadingSubmit.value = null
            },
        }
    )
}
const onDropImage = (event: DragEvent, category_box: any) => {
    const dataRowImage = JSON.parse(event.dataTransfer?.getData('application/json') || '{}')

    // console.log('111 Files dropped:', dataRowImage?.image?.original)
    // console.log('222 Files dropped:', dataRowImage)
    // console.log('222 client:', category_box)
    
    if (dataRowImage) {
        onSubmitImage(dataRowImage, category_box)
    }
}
const onSubmitVideoUrl = () => {

    router[props.imagesUpdateRoute.method || 'patch'](
        route(props.imagesUpdateRoute.name, props.imagesUpdateRoute.parameters),
        {
            [selectedVideoToUpdate.value.column_in_db]: selectedVideoToUpdate.value?.url,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['images_category_box'],
            onStart: () => { 
                loadingSubmit.value = 'video'
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit the data"),
                    type: "success"
                })
                isModalEditVideo.value = false
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set video url"),
                    type: "error"
                })
            },
            onFinish: () => {
                loadingSubmit.value = null
            },
        }
    )
}
const isModalEditVideo = ref(false)
const selectedVideoToUpdate = ref(null)
const _popover = ref(null)
const selectedRow = ref(null)
</script>

<template>
    <div>
        <div v-if="currentTab === 'images' && imagesCategoryBox?.length" class="px-4 py-3">
            <ul xv-if="" class="grid grid-cols-4 md:grid-cols-5 xl:flex gap-x-6 gap-y-2 md:gap-y-8 xl:gap-x-4">
                <li
                    v-for="category_box in imagesCategoryBox"
                    :key="category_box.column_in_db"
                    class="h-fit xxl:h-32 xl:w-24 flex-grow overflow-hidden rounded-xl border border-gray-200"
                >
                    <div
                        class="aspect-square w-full flex items-center justify-center border-b border-gray-900/5 bg-gray-100 relative"
                        @dragover.prevent
                        @drop.prevent="(e) => category_box.type === 'image' ? onDropImage(e, category_box) : undefined"
                        xclass="selectedDragImage ? 'shimmer' : ''"
                    >
                        <Image
                            v-if="category_box.type === 'image' && category_box.images"
                            :src="category_box.images"
                            xclass="max-h-full max-w-full mx-auto object-contain"
                        />

                        <!-- Button: Edit -->
                        <div
                            v-if="category_box.type === 'video'"
                            @click="() => (isModalEditVideo = true, selectedVideoToUpdate = category_box)"
                            v-tooltip="trans('Change url')"
                            class="text-gray-400 hover:text-gray-700 absolute bottom-2 right-2 cursor-pointer">
                            <FontAwesomeIcon icon="fal fa-pencil" class="" fixed-width aria-hidden="true" />
                        </div>

                        <div
                            v-if="loadingSubmit === category_box.column_in_db"
                            class="absolute bg-black/50 text-center text-white text-4xl inset-0 flex items-center justify-center"
                        >
                            <LoadingIcon />
                        </div>
                        <div
                            v-else-if="selectedDragImage && category_box.type === 'image'"
                            class="absolute bg-black/50 text-center text-white text-xl inset-0 flex items-center justify-center"
                        >
                            {{ trans("Drop image here") }}
                        </div>
                    </div>

                    <dl class="-my-3 text-center font-medium text-xs md:text-base divide-y divide-gray-100 px-2 md:px-6 xl:px-2 py-4">
                        {{ category_box.label }}
                        <FontAwesomeIcon v-if="category_box.information" v-tooltip="category_box.information" icon="fal fa-info-circle" class="text-sm text-gray-400 hover:text-gray-700" fixed-width aria-hidden="true" />
                    </dl>
                </li>
            </ul>
        </div>

        <Table :resource="dataTable" name="images" class="mt-5">
            <template #cell(grabable_area)="{ item }">
                <div
                    v-tooltip="trans('Drag and drop to the box to set image')"
                    class="px-2 py-1 text-gray-400 hover:text-gray-700 cursor-grab"
                    draggable="true"
                    @dragstart="(e) => onStartDrag(e, item)"
                    @dragend="(e) => selectedDragImage = null"
                >
                    <FontAwesomeIcon icon="fal fa-grip-horizontal" class="" fixed-width aria-hidden="true" />
                </div>

                <div
                    @click="(e) => (_popover?.toggle(e), selectedRow = item)"
                    v-tooltip="trans('Add image to category:')"
                    class="px-2 py-1 text-gray-400 hover:text-gray-700 cursor-pointer"
                    draggable="true"
                >
                    <FontAwesomeIcon icon="fal fa-arrow-right" class="" fixed-width aria-hidden="true" />
                </div>
            </template>

            <template #cell(image)="{ item: image }">
                <Image :src="image['image']"  />
            </template>

            <template #cell(scope)="{ item: image }">
                {{ image["scope"] }}
            </template>

            <template #cell(caption)="{ item: image }">
                {{ image["caption"] }}
            </template>

            <template #cell(name)="{ item: image }">
                <pre>{{ image.data}}</pre>
            </template>
        </Table>

        <Popover ref="_popover">
            <div class="w-64 relative">
                <div class="text-sm mb-2">
                    {{ trans("Add image to:") }}:
                </div>

                <div class="space-y-2">
                    <Button v-for="category_box in imagesCategoryBox"
                        :key="category_box.column_in_db"
                        @click="() => onSubmitImage(selectedRow, category_box)"
                        type="tertiary"
                        xlabel="channel.customer_sales_channel_name + `${channel.platform_name}`"
                        full
                        :disabled="category_box.type === 'video'"
                        :loading="loadingSubmit === category_box.column_in_db">
                        <template #label>
                            <div class="flex items-center gap-2">
                                {{ category_box.label }}
                            </div>
                        </template>
                    </Button>
                </div>

            </div>
        </Popover>

        <Modal :isOpen="isModalEditVideo" @onClose="() => (isModalEditVideo = false)" width="max-w-2xl w-full">
            <div class="p-6">
                <h2 class="text-3xl font-bold text-center mb-6">{{ trans("Video URL") }}</h2>

                <div class="space-y-6">
                    <div>
                        <label for="amount" class="block text-gray-700 font-medium mb-2">
                            {{ trans("Input video url") }}
                            <p class="inline text-base text-gray-400 italic xmb-6 text-center">({{ trans("Youtube url or Vimeo url") }})</p>:
                        </label>
                        <PureInput
                            :modelValue="selectedVideoToUpdate?.url"
                            @update:modelValue="(value) => set(selectedVideoToUpdate, ['url'], value)"
                            :placeholder="trans('https://example.com/video.mp4')"
                            class="w-full"
                            @keydown.enter="() => onSubmitVideoUrl()"
                            :isLoading="loadingSubmit === 'video'"
                        />
                    </div>
                </div>

                <div class="mt-8 flex justify-end space-x-4">
                    <Button
                        :label="trans('Cancel')"
                        type="negative"
                        @click="() => isModalEditVideo = false"
                    />

                    <Button
                        :label="trans('Submit')"
                        type="primary"
                        @click="() => onSubmitVideoUrl()"
                        full
                        :loading="loadingSubmit === 'video'"
                    />
                </div>
            </div>
        </Modal>
    </div>
</template>