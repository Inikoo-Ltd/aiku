<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal } from "@fal"
import { computed, defineAsyncComponent, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import TradeUnitShowcase from "@/Components/Goods/TradeUnitShowcase.vue"
import { routeType } from "@/types/route"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import TableStocks from "@/Components/Tables/Grp/Goods/TableStocks.vue"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import type { Navigation } from "@/types/Tabs"
import TableImages from "@/Components/Tables/Grp/Helpers/TableImages.vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { Images } from "@/types/Images"
import Modal from "@/Components/Utils/Modal.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import Image from "@/Components/Image.vue"
import { set } from "lodash"

library.add(faInventory, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal)

const isModalUploadOpen = ref(false)
const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: {
        current: string;
        navigation: Navigation
    }
    showcase?: object,
    attachments?: {}
    attachmentRoutes?: {}
    tag_routes: {
        store_tag: routeType
        update_tag: routeType
        destroy_tag: routeType
        attach_tag: routeType
        detach_tag: routeType
    }
    products?: {}
    stocks?: {}
    images?: {},
    images_category_box?: { 
        label: string
        type: string
        key_in_db: string
        url?: string
        images?: Images
    }[]
    images_update_route: routeType

}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        showcase: TradeUnitShowcase,
        history: ModelChangelog,
        attachments: TableAttachments,
        products: TableProducts,
        stocks: TableStocks,
        images: TableImages
    }
    return components[currentTab.value]

})


// const clients = ref([
//     {
//         id: 1,
//         name: "Client 1",
//         imageUrl: "https://placehold.co/400x700",
//         lastInvoice: {
//             dateTime: "2023-10-01T12:00:00Z",
//             date: "October 1, 2023",
//             amount: "$100.00",
//             status: "paid"
//         }
//     },
//     {
//         id: 2,
//         name: "Client 2",
//         imageUrl: "https://placehold.co/1100x700",
//         lastInvoice: {
//             dateTime: "2023-10-02T12:00:00Z",
//             date: "October 2, 2023",
//             amount: "$200.00",
//             status: "unpaid"
//         }
//     }
// ])


const selectedDragImage = ref(null)
function onStartDrag(event: DragEvent, img: ImageData) {
    selectedDragImage.value = img
    const data = JSON.stringify(img)
    console.log('Dropping data:', data)
    event.dataTransfer?.setData('application/json', data)
}

const loadingSubmit = ref<null | number | string>(null)

const onDropImage = (event: DragEvent, client: any) => {
    const dataRowImage = JSON.parse(event.dataTransfer?.getData('application/json') || '{}')

    console.log('111 Files dropped:', dataRowImage?.image?.original)
    console.log('222 Files dropped:', dataRowImage)
    console.log('222 client:', client)
    
    if (dataRowImage) {
        client.imageUrl = dataRowImage?.image?.original
        router[props.images_update_route.method || 'patch'](
            route(props.images_update_route.name, props.images_update_route.parameters),
            {
                [client.key_in_db]: dataRowImage.id,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['images_category_box'],
                onStart: () => { 
                    loadingSubmit.value = 'true'
                },
                onSuccess: () => {
                    notify({
                        title: trans("Success"),
                        text: trans("Successfully submit the data"),
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
}
const isModalEditVideo = ref(false)
const selectedVideoToUpdate = ref(null)
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="currentTab === 'attachments'" @click="() => isModalUploadOpen = true" label="Attach" icon="upload" />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <div v-if="currentTab === 'images' && images_category_box?.length" class="px-4 py-3">
        <ul xv-if="" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-x-6 gap-y-8 xl:gap-x-8">
            <li
                v-for="category_box in images_category_box"
                :key="category_box.id"
                class="h-fit overflow-hidden rounded-xl border border-gray-200"
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
                    <!-- <div
                        v-if="category_box.type === 'video'"
                        @click="() => (isModalEditVideo = true, selectedVideoToUpdate = category_box)"
                        v-tooltip="trans('Change url')"
                        class="text-gray-400 hover:text-gray-700 absolute bottom-2 right-2 cursor-pointer">
                        <FontAwesomeIcon icon="fal fa-pencil" class="" fixed-width aria-hidden="true" />
                    </div> -->

                    <div
                        v-if="selectedDragImage && category_box.type === 'image'"
                        class="absolute bg-black/50 text-center text-white text-xl inset-0 flex items-center justify-center"
                    >
                        {{ trans("Drop image here") }}
                    </div>
                </div>

                <dl class="-my-3 text-center font-medium text-base divide-y divide-gray-100 px-6 py-4">
                    {{ category_box.label }}
                    <FontAwesomeIcon v-if="category_box.information" v-tooltip="category_box.information" icon="fal fa-info-circle" class="text-sm text-gray-400 hover:text-gray-700" fixed-width aria-hidden="true" />
                </dl>
            </li>
        </ul>
    </div>

    <!-- <div class="mb-6 grid grid-cols-3 gap-4">
        <div
            v-for="img in availableImages"
            :key="img.id"
            class="p-2 border rounded shadow bg-white cursor-move"
            draggable="true"
            @dragstart="(e) => onStartDrag(e, img)"
            @dragend="(e) => selectedDragImage = null"
        >
            <img :src="img.url" class="w-full h-auto object-contain" />
            <p class="text-center text-sm mt-2">{{ img.name }}</p>
        </div>
    </div> -->

    <Table v-if="currentTab === 'images'" :resource="images" name="images" class="mt-5">
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

    <component v-else
        :is="component"
        :data="props[currentTab]"
        :tab="currentTab"
        :tag_routes
        :detachRoute="attachmentRoutes.detachRoute">
    </component>

    <!-- Modal: Increase balance -->
    <!-- <Modal :isOpen="isModalEditVideo" @onClose="() => (isModalEditVideo = false)" width="max-w-2xl w-full">
        <div class="p-6">
            <h2 class="text-3xl font-bold text-center">{{ trans("Increase Balance") }}</h2>
            <p class="text-base text-gray-500 italic mb-6 text-center">{{ trans("Enter the details to increase balance") }}</p>

            <div class="space-y-6">
                <div>
                    <label for="amount" class="block text-gray-700 font-medium mb-2">
                        {{ trans("Reason to deposit") }}
                    </label>
                    <PureInput
                        :modelValue="selectedVideoToUpdate?.url"
                        @update:modelValue="(value) => set(selectedVideoToUpdate, ['url'], value)"
                        :placeholder="trans('Input your video url')"
                        class="w-full"
                    />
                </div>
            </div>

            <div class="mt-8 flex justify-end space-x-4">
                <Button
                    :label="trans('Cancel')"
                    type="negative"
                    @click="() => closeModal()"
                >
                </Button>

                <Button
                    :label="trans('Submit')"
                    type="primary"
                    @click="() => onSubmitIncrease()"
                    full
                >
                </Button>
            </div>
        </div>
    </Modal> -->


    <UploadAttachment v-model="isModalUploadOpen" scope="attachment" :title="{
        label: 'Upload your file',
        information: 'The list of column file: customer_reference, notes, stored_items'
    }" progressDescription="Adding Pallet Deliveries" :attachmentRoutes="attachmentRoutes" />
</template>
