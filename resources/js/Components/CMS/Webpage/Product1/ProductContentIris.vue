<script setup lang="ts">
import { computed, ref } from 'vue'
import { faChevronDown } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

const props = defineProps<{
    product: {
        id: number
        name: string
        description: string
        images: { source: string }[]
        contents: {
            data: Array<{ id: number; title: string; text: string; type: 'information' | 'faq' }>
        }
    }
      setting : {
        product_specs : boolean,
        faqs : boolean
    }
}>()


const localContents = ref(props.product.contents)

const informationContents = computed(() =>
    localContents.value.filter((item) => item.type === 'information')
)

const faqContents = computed(() =>
    localContents.value.filter((item) => item.type === 'faq')
)


const openDisclosureId = ref<number | null>(null)

</script>

<template>
    <div class="w-full">
        <div v-if="setting.product_specs" class="mb-6">
            <div class="space-y-3">
                <template v-for="content in informationContents" :key="content.id">
                    <div class="relative">
                        <div @click="openDisclosureId = openDisclosureId === content.id ? null : content.id"
                            class="w-full sm:w-7/12 mb-1 border-b border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center cursor-pointer">
                            <div v-html="content.title"></div>
                            <FontAwesomeIcon :icon="faChevronDown"
                                class="text-sm text-gray-500 transform transition-transform duration-200"
                                :class="{ 'rotate-180': openDisclosureId === content.id }" />
                        </div>
                        <div v-show="openDisclosureId === content.id" class="text-sm text-gray-600">
                            <div v-html="content.text"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- FAQ Section -->
        <div v-if="setting.faqs" class="mb-6 relative">
            <div v-if="faqContents.length > 0" class="text-sm text-gray-500 mb-1 font-semibold">Frequently Asked Questions (FAQs)</div>
            <div class="space-y-2">
                <template v-for="content in faqContents" :key="content.id">
                    <div class="relative hover:bg-gray-50 rounded transition">
                        <div @click="openDisclosureId = openDisclosureId === content.id ? null : content.id"
                            class="w-full sm:w-7/12 mb-1 border-b border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center cursor-pointer">
                            <div v-html="content.title"></div>
                            <div class="flex items-center gap-4">
                                <FontAwesomeIcon :icon="faChevronDown"
                                    class="text-sm text-gray-500 transform transition-transform duration-200"
                                    :class="{ 'rotate-180': openDisclosureId === content.id }" />
                            </div>
                        </div>
                        <div v-show="openDisclosureId === content.id" class="text-sm text-gray-600">
                            <div v-html="content.text"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
