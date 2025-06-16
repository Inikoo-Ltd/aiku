<script setup lang="ts">
import { computed, ref } from 'vue'
import { faChevronDown, faPlus, faTrash, faExclamationTriangle } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { useConfirm } from 'primevue/useconfirm'
import ConfirmDialog from 'primevue/confirmdialog'
import cloneDeep from 'lodash-es/cloneDeep'
import { debounce } from 'lodash-es'

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

const confirm = useConfirm()
const cancelToken = ref<any>(null)
const localContents = ref(cloneDeep(props.product.contents.data))

const informationContents = computed(() =>
    localContents.value.filter((item) => item.type === 'information')
)

const faqContents = computed(() =>
    localContents.value.filter((item) => item.type === 'faq')
)

const onAddContent = (data: { title: string; text: string; type: 'information' | 'faq'; position: number }) => {
    router.post(
        route('grp.models.product.content.store', { product: props.product.id }),
        data,
        {
            preserveScroll: false,
            onCancelToken: (token) => (cancelToken.value = token.cancel),
            onFinish: () => (cancelToken.value = null),
            onSuccess: () => {
                notify({ title: 'Success', text: 'Content added successfully.', type: 'success' })
                router.reload({ only: ['product'] })
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

const onUpdateContent = (id: number, payload: { title?: string; text?: string }) => {
    router.patch(route('grp.models.model_has_content.update', { modelHasContent: id }), payload, {
        preserveScroll: true,
        onSuccess: () => {
            notify({ title: 'Updated', text: 'Content updated successfully.', type: 'success' })
        },
        onError: () => {
            notify({ title: 'Error', text: 'Failed to update content.', type: 'error' })
        },
    })
}

const debouncedUpdate = debounce((id: number, payload) => {
    onUpdateContent(id, payload)
}, 800)

const updateLocalContent = (id: number, key: 'title' | 'text', value: string) => {
    const item = localContents.value.find((c) => c.id === id)
    if (item) item[key] = value
    debouncedUpdate(id, { [key]: value })
}

const addInformation = () => {
    onAddContent({
        title: 'Product Information',
        text: 'Write your product details here.',
        type: 'information',
        position: 0,
    })
}

const addFAQ = () => {
    onAddContent({
        title: 'New FAQ',
        text: 'Answer your customer questions here.',
        type: 'faq',
        position: faqContents.value.length + 1,
    })
}

const onDeleteContent = (id: number) => {
    router.delete(route('grp.models.model_has_content.delete', { modelHasContent: id }), {
        preserveScroll: true,
        onSuccess: () => {
            notify({ title: 'Deleted', text: 'Content has been deleted.', type: 'success' })
            router.reload({ only: ['product'] })
        },
        onError: () => {
            notify({ title: 'Error', text: 'Failed to delete content.', type: 'error' })
        },
    })
}

const confirmDelete = (id: number) => {
    confirm.require({
        message: 'Are you sure you want to delete this content?',
        header: 'Confirm Delete',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => onDeleteContent(id),
    })
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
                            class="w-7/12 mb-1 border-b border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center">
                            <EditorV2
                                :modelValue="content.title"
                                @update:model-value="(value) => updateLocalContent(content.id, 'title', value)"
                            />
                            <FontAwesomeIcon :icon="faChevronDown"
                                class="text-sm text-gray-500 transform transition-transform duration-200"
                                :class="{ 'rotate-180': open }" />
                        </DisclosureButton>
                        <DisclosurePanel class="text-sm text-gray-600">
                            <EditorV2
                                :modelValue="content.text"
                                @update:model-value="(value) => updateLocalContent(content.id, 'text', value)"
                            />
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
                    <div class="relative group">
                        <DisclosureButton
                            class="w-7/12 mb-1 border-b border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center">
                            <EditorV2
                                :modelValue="content.title"
                                @update:model-value="(value) => updateLocalContent(content.id, 'title', value)"
                            />
                            <div class="flex items-center gap-4">
                                <button @click.stop="confirmDelete(content.id)"
                                    class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity"
                                    title="Delete Content">
                                    <FontAwesomeIcon :icon="faTrash" />
                                </button>
                                <FontAwesomeIcon :icon="faChevronDown"
                                    class="text-sm text-gray-500 transform transition-transform duration-200"
                                    :class="{ 'rotate-180': open }" />
                            </div>
                        </DisclosureButton>
                        <DisclosurePanel class="text-sm text-gray-600">
                            <EditorV2
                                :modelValue="content.text"
                                @update:model-value="(value) => updateLocalContent(content.id, 'text', value)"
                            />
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

    <ConfirmDialog>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmDialog>
</template>
