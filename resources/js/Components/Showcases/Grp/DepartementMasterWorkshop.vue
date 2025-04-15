<script setup lang="ts">
import { ref, inject } from 'vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Dialog from 'primevue/dialog'
import SwiperImage from '@/Components/Elements/SwiperImage.vue'
import Modal from '@/Components/Utils/Modal.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPlug, faPlus, faPlusCircle } from '@far'
import { layoutStructure } from "@/Composables/useLayoutStructure"
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
const locale = inject('locale', layoutStructure)
const visible = ref(false)
const isModalGallery = ref(false)




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
                    <h2 class="text-lg font-semibold text-gray-900">{{ product.name }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ product.description }}</p>
                </div>>
            </div>

        </div>
    </div>




    <Modal :isOpen="isModalGallery" @onClose="() => (isModalGallery = false)" width="w-3/4">
        <GalleryManagement :uploadRoute="{ name: '', parameters: '' }" :closePopup="() => (isModalGallery = false)" />
    </Modal>
</template>
