<script setup lang="ts">
import { ref, inject } from "vue";
import PureInput from '@/Components/Pure/PureInput.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Dialog from 'primevue/dialog'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPlus, } from '@far'
import { layoutStructure } from "@/Composables/useLayoutStructure"


import { routeType } from "@/types/route";

import SetVisibleList from '@/Components/Departement&Family/SetVisibleList.vue';
import EditPreviewBluprintData from "@/Components/Departement&Family/EditPreviewBluprintData.vue";

const props = defineProps<{
    pageHead: {}
    assets: any
    web_block_types: Array<string>
    web_block_types_families: {
        data: Array
    }
    update_route: routeType
    upload_image_route: routeType
    family: {
        data: {
            code: string
            name: string
            description: string
            images: Array<string>
            show_in_website: boolean
        }
    }
}>()
console.log(props)

const locale = inject('locale', layoutStructure)
const visible = ref(false)
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
    <PageHeading :data="pageHead" />
    <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <EditPreviewBluprintData title="Families List" :data="family.data" :update_route="update_route"
                :web_block_types="web_block_types" :upload_image_route="upload_image_route">
            </EditPreviewBluprintData>
            <SetVisibleList title="Products List" :list_data="assets.data" :updateRoute="update_route" />
            <div class="bg-white  p-6 h-fit rounded-2xl shadow-md border border-gray-200">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-800">FAQ</h2>

                    <div class="w-40">
                        <label class="sr-only">Select Language</label>
                        <select v-model="translation"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option v-for="item of locale.languageOptions" :value="item.code">{{ item.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Divider -->
                <hr class="border-t border-gray-200 mb-4" />

                <div class="space-y-4">
                    <Disclosure v-for="(faq, index) in faqs" :key="index" v-slot="{ open }">
                        <DisclosureButton
                            class="flex justify-between w-full px-4 py-2 text-sm font-medium text-left text-blue-900 bg-blue-100 rounded-lg hover:bg-blue-200 focus:outline-none focus-visible:ring focus-visible:ring-blue-500 focus-visible:ring-opacity-75">
                            {{ faq.question }}
                            <span>{{ open ? '−' : '+' }}</span>
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pt-4 pb-2 text-sm text-gray-600">
                            {{ faq.answer }}
                        </DisclosurePanel>
                    </Disclosure>

                    <Button full label="Create" :icon="faPlus" :type="'dashed'" @click="visible = true" />
                </div>
            </div>
        </div>

    </div>

    <!-- Dialog to Add FAQ -->
    <Dialog v-model:visible="visible" header="Add FAQ" :style="{ width: '40rem' }" modal>

        <!-- Question Input -->
        <div class="items-center gap-4 mb-4">
            <label for="question" class="font-semibold w-24 mb-4">Question</label>
            <PureInput v-model="newFaq.question" id="question" placeholder="Enter question" />
        </div>

        <!-- Answer Input -->
        <div class="items-center gap-4 mb-8">
            <label for="answer" class="font-semibold w-24 mb-4">Answer</label>
            <PureTextarea v-model="newFaq.answer" id="answer" placeholder="Enter answer" rows="4" />
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-2">
            <Button :type="'tertiary'" label="Cancel" @click="visible = false" />
            <Button type="save" @click="addFaq" />
        </div>
    </Dialog>

</template>
