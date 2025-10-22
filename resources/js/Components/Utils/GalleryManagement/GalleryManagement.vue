<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { inject, ref } from 'vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import GalleryUpload from '@/Components/Utils/GalleryManagement/GalleryUpload.vue'
import GalleryUploadedImages from '@/Components/Utils/GalleryManagement/GalleryUploadedImages.vue'
import axios from 'axios'
import { faCube, faStar, faImage } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { ImageData } from '@/types/Image'
library.add(faCube, faStar, faImage)

const layout = inject('layout', layoutStructure)
const props = withDefaults(defineProps<{
    //props for all
    tabs: string[];
    multiple: boolean;
    maxSelected?: number;
    submitUpload?:Function
    isLoadingSubmit?: boolean;
    //propsforUpload 
    uploadRoute: routeType;
    //stockImages
    stockImagesRoute?: routeType;
    //images uploded
    imagesUploadedRoutes?: routeType;
    attachImageRoute: routeType;
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
            console.log(galleryUploadRef.value)
            galleryUploadRef.value.fileUploadRef.uploadedFiles = files
            galleryUploadRef.value.fileUploadRef.files = []
        }
        notify({
            title: trans('Success'),
            text: trans('New image added'),
            type: 'success',
        });
    } catch (error) {
        console.log(error)
        uploadProgress.value = null
        notify({
            title: trans('Something went wrong'),
            text: trans('Failed to add new image'),
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
    <div>
        <TabGroup :selectedIndex="selectedTab" @change="(index) => selectedTab = index">
            <TabList class="flex space-x-8 border-b-2">
                <Tab as="template" v-slot="{ selected }" v-if="tabs.includes('upload')">
                    <button
                        :style="selected ? { color: layout.app.theme[0], borderBottomColor: layout.app.theme[0] } : {}"
                        class="whitespace-nowrap border-b-2 py-1.5 px-1 text-sm font-medium focus:ring-0 focus:outline-none mb-2">
                        Upload
                    </button>
                </Tab>
                <Tab as="template" v-slot="{ selected }" v-if="tabs.includes('images_uploaded')">
                    <button
                        :style="selected ? { color: layout.app.theme[0], borderBottomColor: layout.app.theme[0] } : {}"
                        class="whitespace-nowrap border-b-2 py-1.5 px-1 text-sm font-medium focus:ring-0 focus:outline-none mb-2">
                        Images Uploaded
                    </button>
                </Tab>
                <Tab as="template" v-slot="{ selected }" v-if="tabs.includes('stock_images')">
                    <button
                        :style="selected ? { color: layout.app.theme[0], borderBottomColor: layout.app.theme[0] } : {}"
                        class="whitespace-nowrap border-b-2 py-1.5 px-1 text-sm font-medium focus:ring-0 focus:outline-none mb-2">
                        Stock Images
                    </button>
                </Tab>
            </TabList>

            <TabPanels class="mt-2">
                <TabPanel class="h-full rounded-xl bg-white p-3" v-if="tabs.includes('upload')">
                    <GalleryUpload
                        ref="galleryUploadRef"
                        :fileLimit="maxSelected"
                        :isLoading="props.isLoadingSubmit || isLoading"
                        @onSubmitUpload="beforeSubmitImage"
                        accept="image/*" 
                        name="image" 
                        :uploadProgress="uploadProgress"
                    />
                </TabPanel>
                <TabPanel class="h-full rounded-xl bg-white p-3" v-if="tabs.includes('images_uploaded')">
                    <GalleryUploadedImages
                        :imagesUploadedRoutes
                        :attachImageRoute
                        :maxSelected
                        @selectImage="(image) => emits('selectImage', image)"
                        @submitSelectedImages="(images) => emits('submitSelectedImages', images)"
                    />
                </TabPanel>
                <TabPanel class="h-full rounded-xl bg-white p-3" v-if="tabs.includes('stock_images')">
                    <GalleryUploadedImages
                        :imagesUploadedRoutes="stockImagesRoute"
                        :attachImageRoute
                        :maxSelected
                        @selectImage="(image) => emits('selectImage', image)"
                        @submitSelectedImages="(images) => emits('submitSelectedImages', images)"
                    />
                </TabPanel>
            </TabPanels>
        </TabGroup>
    </div>
</template>