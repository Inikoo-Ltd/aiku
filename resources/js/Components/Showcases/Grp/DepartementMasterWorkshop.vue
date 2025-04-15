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
        family: {
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
const faqs = ref([
    {
        question: 'Shipping & Returns',
        answer: 'We offer free shipping on orders over $100 and a 30-day return policy for all purchases.'
    },
    {
        question: 'Product Materials',
        answer: 'Made with durable canvas and full-grain leather to ensure long-lasting use and a sleek look.'
    },
    {
        question: 'Warranty Information',
        answer: 'All products come with a 1-year limited warranty covering manufacturing defects.'
    },
    {
        question: 'Care Instructions',
        answer: 'Wipe clean with a damp cloth. Do not machine wash or tumble dry.'
    }
])
const translation = ref("english")
const family = ref(props.data.family.data)

const newFaq = ref({
    question: '',
    answer: ''
})


const addFaq = () => {
    if (newFaq.value.question && newFaq.value.answer) {
        faqs.value.push({ ...newFaq.value })
        newFaq.value.question = ''
        newFaq.value.answer = ''
        visible.value = false
    }
}


</script>

<template>
    <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left: Product Form -->
            <div class="bg-white rounded-xl shadow-md  pt-0 space-y-6 w-full h-full overflow-hidden">

                <!-- Image -->
                <div class="relative">
                    <Button type="primary" label="Upload" :icon="faPlusCircle" :size="'xs'" @click="isModalGallery = true"
                        class="absolute top-2 right-2 z-10 bg-white text-sm border rounded px-3 py-1 cursor-pointer shadow" />

                    <!-- Image Swiper Display -->
                    <div class="flex justify-center items-start">
                        <SwiperImage :images="images" />
                    </div>
                </div>

                <div class="px-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Family Name</label>
                    <PureInput v-model="family.name" placeholder="Enter product name" />
                </div>

                <div class="px-6 pb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Family Description</label>
                    <PureTextarea v-model="family.description" rows="4" placeholder="Enter product description" />
                </div>
            </div>

        </div>
    </div>




    <Modal :isOpen="isModalGallery" @onClose="() => (isModalGallery = false)" width="w-3/4">
		<GalleryManagement
			:uploadRoute="{ name :'', parameters : ''}"
			:closePopup="() => (isModalGallery = false)"
			/>
	</Modal>
</template>
