<script setup lang="ts">
import { ref, inject } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Dialog from 'primevue/dialog'
import SwiperImage from '@/Components/Elements/SwiperImage.vue'
import Modal from '@/Components/Utils/Modal.vue'

import { faPlug, faPlus, faPlusCircle } from '@far'
import GalleryManagement from '@/Components/Utils/GalleryManagement/GalleryManagement.vue'

const props = defineProps<{
    data: {
        departement: {
            data: {
                code: string
                name: string
                description: string
                images: Array<string>
            }
        }

    }
}>()

const visible = ref(false)
const isModalGallery = ref(false)

console.log(props.data)
const images = [
    {image : 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png'},
    {image : 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png'},
    {image : 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png'},
    {image : 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png'},
    {image : 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png'},
    {image : 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png'},
    {image : 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png'},
]

</script>

<template>
    <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left: Product Form -->
            <div class="bg-white rounded-xl shadow-md  pt-0 space-y-6 w-full h-full overflow-hidden">
                <div class="relative">
                    <Button type="primary" label="Upload" :icon="faPlusCircle" :size="'xs'"
                        @click="isModalGallery = true"
                        class="absolute top-2 right-2 z-10 bg-white text-sm border rounded px-3 py-1 cursor-pointer shadow" />
                    <div class="flex justify-center items-start">
                        <SwiperImage :images="images" />
                    </div>
                </div>

                <div class="p-4">
                    <h2 class="text-lg font-semibold text-gray-900">departement name</h2>
                    <p class="mt-2 text-sm text-gray-600">Lorem Ipsum is simply dummy text of the
                         printing and typesetting industry. Lorem Ipsum has been the industry's 
                         standard dummy text ever since the 1500s, when an unknown printer took a 
                         galley of type and scrambled it to make a type specimen book. It has survived 
                         not only five centuries, but also the leap into electronic typesetting, remaining 
                         essentially unchanged. It was popularised in the 1960s with the release of Letraset 
                         sheets containing Lorem Ipsum passages, and more recently with desktop publishing 
                         software like Aldus PageMaker including versions of Lorem Ipsum.</p>
                </div>
            </div>

        </div>
    </div>




    <Modal :isOpen="isModalGallery" @onClose="() => (isModalGallery = false)" width="w-3/4">
        <GalleryManagement :uploadRoute="{ name: '', parameters: '' }" :closePopup="() => (isModalGallery = false)" />
    </Modal>
</template>
