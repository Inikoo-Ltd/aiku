<script setup lang="ts">
import { computed, ref } from 'vue'
import { faChevronDown, faPlus } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'

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
}>()

const cancelToken = ref<any>(null)

const informationContents = computed(() =>
    props.product.contents.data.filter((item) => item.type === 'information')
)

const faqContents = computed(() =>
    props.product.contents.data.filter((item) => item.type === 'faq')
)

const onAddContent = (data: { title: string; text: string; type: 'information' | 'faq' }) => {
    router.post(
        route('grp.models.product.content.store',{product: props.product.id}),
        data,
        {
            preserveScroll: false,
            onCancelToken: (token) => {
                cancelToken.value = token.cancel
            },
            onFinish: () => {
                cancelToken.value = null
            },
            onSuccess: () => {
                notify({
                    title: 'Success',
                    text: 'Content added successfully.',
                    type: 'success',
                })
            },
            onError: (error) => {
                notify({
                    title: 'Something went wrong',
                    text: error?.message ?? 'An error occurred while saving.',
                    type: 'error',
                })
            },
        }
    )
}

const addInformation = () => {
    const data = {
        title: 'Product Information',
        text: 'Write your product details here.',
        type: 'information',
        position: 0, // Always zero for information
    }
    onAddContent(data)
}

const addFAQ = () => {
    const position = faqContents.value.length + 1 // Or use any custom logic
    const data = {
        title: 'New FAQ',
        text: 'Answer your customer questions here.',
        type: 'faq',
        position,
    }
    onAddContent(data)
}

</script>

<template>
    <div class="w-full relative group">
        <!-- Product Information Section -->
        <div class="mb-6">
            <div v-if="informationContents.length === 0"
                class="text-center text-gray-500 text-sm py-6 italic border border-dashed border-gray-300 rounded">
                <div class="py-2">No product information yet. Click the add button to insert new content.</div>
                <Button type="secondary" :icon="faPlus" label="Add Information" @click="addInformation" />
            </div>

            <div v-else class="space-y-3">
                <Disclosure v-for="content in informationContents" :key="content.id" v-slot="{ open }">
                    <div>
                        <DisclosureButton
                            class="w-full mb-1 border-b border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center">
                            {{ content.title }}
                            <FontAwesomeIcon :icon="faChevronDown"
                                class="text-sm text-gray-500 transform transition-transform duration-200"
                                :class="{ 'rotate-180': open }" />
                        </DisclosureButton>
                        <DisclosurePanel class="text-sm text-gray-600">
                            <p v-html="content.text" />
                        </DisclosurePanel>
                    </div>
                </Disclosure>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="mb-6">
            <div class="text-sm text-gray-500 mb-1 font-semibold">Frequently Asked Questions (FAQs)</div>

            <div v-if="faqContents.length === 0"
                class="text-center text-gray-500 text-sm py-6 italic border border-dashed border-gray-300 rounded">
                <div class="py-2">No FAQs yet. Click the add button to include some questions.</div>
                <Button type="secondary" :icon="faPlus" label="Add FAQ" @click="addFAQ" />
            </div>

            <div v-else class="space-y-3">
                <Disclosure v-for="content in faqContents" :key="content.id" v-slot="{ open }">
                    <div>
                        <DisclosureButton
                            class="w-full mb-1 border-b border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center">
                            {{ content.title }}
                            <FontAwesomeIcon :icon="faChevronDown"
                                class="text-sm text-gray-500 transform transition-transform duration-200"
                                :class="{ 'rotate-180': open }" />
                        </DisclosureButton>
                        <DisclosurePanel class="text-sm text-gray-600">
                            <p v-html="content.text" />
                        </DisclosurePanel>
                    </div>
                </Disclosure>
            </div>
        </div>

        <!-- Optional Hover-Only Add Button -->
        <div v-if="faqContents.length > 0"
            class="flex justify-center mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
            <button @click="addFAQ"
                class="w-full text-sm flex items-center justify-center gap-2 text-indigo-600 hover:text-indigo-800 border border-indigo-600 hover:border-indigo-800 rounded px-3 py-2 transition-colors"
                title="Add FAQ">
                <FontAwesomeIcon :icon="faPlus" />
                <span>Add FAQ</span>
            </button>
        </div>
    </div>
</template>
