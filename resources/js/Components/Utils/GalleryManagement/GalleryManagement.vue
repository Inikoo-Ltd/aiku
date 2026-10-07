<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { computed, inject, ref } from 'vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import GalleryUpload from '@/Components/Utils/GalleryManagement/GalleryUpload.vue'
import GalleryUploadedImages from '@/Components/Utils/GalleryManagement/GalleryUploadedImages.vue'
import axios from 'axios'
import { faCube, faStar, faImage } from "@fas"
import { faCloudUpload, faImages, faPhotoVideo, faCopyright, faBooks } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import { ImageData } from '@/types/Image'
library.add(faCube, faStar, faImage, faCloudUpload, faImages, faPhotoVideo, faCopyright, faBooks)

const layout = inject('layout', layoutStructure)
const props = withDefaults(defineProps<{
    //props for all
    tabs: string[];
    multiple?: boolean;
    maxSelected?: number;
    submitUpload?:Function
    isLoadingSubmit?: boolean;
    //propsforUpload 
    uploadRoute?: routeType;
    //stockImages
    stockImagesRoute?: routeType;
    //images uploded
    imagesUploadedRoutes?: routeType;
    imagesUploadedLabel?: string;
    attachImageRoute?: routeType;
    imageCategories?: Array<{ key: string, label: string, route: routeType, icon?: string }>;
    fill?: boolean;
}>(), {
    multiple: false,
    tabs: () => ['upload', 'images_uploaded', 'stock_images'],
    stockImagesRoute: () => ({
        name: 'grp.gallery.stock-images.index'
    }),
    imagesUploadedRoutes: () => ({
        name: 'grp.gallery.uploaded-images.index'
    }),
});


const visibleTabs = computed(() => [
    ...(props.tabs.includes('upload') ? [{ key: 'upload', label: ctrans('Upload'), icon: 'fal fa-cloud-upload', route: undefined }] : []),
    ...(props.imageCategories ?? []).map((category) => ({ ...category, key: `category-${category.key}` })),
    ...(props.tabs.includes('images_uploaded') ? [{ key: 'images_uploaded', label: props.imagesUploadedLabel ?? ctrans('Images Uploaded'), icon: 'fal fa-images', route: props.imagesUploadedRoutes }] : []),
    ...(props.tabs.includes('stock_images') ? [{ key: 'stock_images', label: ctrans('Stock Images'), icon: 'fal fa-photo-video', route: props.stockImagesRoute }] : []),
])

const selectedTab = ref(0)
const galleryUploadRef = ref(null);
const uploadProgress = ref(0);

const emits = defineEmits<{
    (e: 'onSuccessUpload', value: {}): void
    (e: 'submitSelectedImages', value: ImageData[]): void
    (e: 'selectImage', value: {}): void
}>()


const isLoading = ref(false)

const onSubmitUpload = async (files: File[]) => {
    const formData = new FormData();
    files.forEach((file, index) => {
        formData.append(`images[${index}]`, file);
    });

    try {
        isLoading.value = true;
        uploadProgress.value = 0;
        const response = await axios.post(
            route(props.uploadRoute.name, props.uploadRoute.parameters),
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total) {
                        uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    }
                },
            }
        );

        emits('onSuccessUpload', response.data);
        uploadProgress.value = null
        if (galleryUploadRef.value) {
            galleryUploadRef.value.fileUploadRef.uploadedFiles = files
            galleryUploadRef.value.fileUploadRef.files = []
        }
        notify({
            title: ctrans('Success'),
            text: ctrans('New image added'),
            type: 'success',
        });
    } catch (error) {
        uploadProgress.value = null
        notify({
            title: ctrans('Something went wrong'),
            text: ctrans('Failed to add new image'),
            type: 'error',
        });
    } finally {
        isLoading.value = false;
        uploadProgress.value = 0;
    }
};

const beforeSubmitImage = (files) => {
    if(props.submitUpload) props.submitUpload(files,galleryUploadRef.value)
    else onSubmitUpload(files)
}

</script>


<template>
    <div :class="fill ? 'flex h-full min-h-0 flex-col' : ''">
        <TabGroup :selectedIndex="selectedTab" @change="(index) => selectedTab = index" as="div" :class="fill ? 'flex min-h-0 flex-1 flex-col' : ''">
            <TabList class="flex shrink-0 flex-wrap gap-x-1 border-b border-gray-200">
                <Tab as="template" v-slot="{ selected }" v-for="tab in visibleTabs" :key="tab.key">
                    <button type="button"
                        :style="selected ? { color: layout.app.theme[0], borderBottomColor: layout.app.theme[0] } : {}"
                        class="-mb-px flex items-center gap-x-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-0"
                        :class="selected ? '' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'">
                        <FontAwesomeIcon v-if="tab.icon" :icon="tab.icon" fixed-width aria-hidden="true" />
                        {{ tab.label }}
                    </button>
                </Tab>
            </TabList>

            <TabPanels :class="fill ? 'mt-3 min-h-0 flex-1' : 'mt-2'">
                <TabPanel v-for="tab in visibleTabs" :key="tab.key" :class="fill ? 'flex h-full flex-col focus:outline-none' : 'h-full rounded-xl bg-white p-3'">
                    <GalleryUpload
                        v-if="tab.key === 'upload'"
                        :ref="(element) => { galleryUploadRef = element }"
                        :fileLimit="maxSelected"
                        :isLoading="props.isLoadingSubmit || isLoading"
                        @onSubmitUpload="beforeSubmitImage"
                        accept="image/*"
                        name="image"
                        :fill
                        :uploadProgress="uploadProgress"
                    />
                    <GalleryUploadedImages
                        v-else
                        :imagesUploadedRoutes="tab.route"
                        :attachImageRoute
                        :maxSelected
                        :fill
                        @selectImage="(image) => emits('selectImage', image)"
                        @submitSelectedImages="(images) => emits('submitSelectedImages', images)"
                    />
                </TabPanel>
            </TabPanels>
        </TabGroup>
    </div>
</template>
