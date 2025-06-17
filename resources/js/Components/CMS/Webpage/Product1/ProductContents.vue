<script setup lang="ts">
import { computed, ref } from 'vue'
import { faChevronDown, faPlus, faExclamationTriangle } from '@fortawesome/free-solid-svg-icons'
import { faTrash } from '@far'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { useConfirm } from 'primevue/useconfirm'
import ConfirmDialog from 'primevue/confirmdialog'
import Skeleton from 'primevue/skeleton'
import { debounce } from 'lodash-es'
import axios from 'axios'

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
const loadingAdd = ref(false)
const loadingDeleteIds = ref<number[]>([])
const localContents = ref(props.product.contents)

const informationContents = computed(() =>
    localContents.value.filter((item) => item.type === 'information')
)

const faqContents = computed(() =>
    localContents.value.filter((item) => item.type === 'faq')
)

const onAddContent = async (data: {
    title: string
    text: string
    type: 'information' | 'faq'
    position: number
}) => {
    const source = axios.CancelToken.source()
    cancelToken.value = source.cancel
    loadingAdd.value = true

    try {
        const response = await axios.post(
            route('grp.models.product.content.store', { product: props.product.id }),
            data,
            { cancelToken: source.token }
        )
        notify({ title: 'Success', text: 'Content added successfully.', type: 'success' })
        localContents.value.push(response.data.data)
    } catch (error: any) {
        if (!axios.isCancel(error)) {
            notify({
                title: 'Something went wrong',
                text: error?.response?.data?.message ?? 'An error occurred while saving.',
                type: 'error',
            })
        }
    } finally {
        loadingAdd.value = false
        cancelToken.value = null
    }
}

const onUpdateContent = (id: number, payload: any) => {
    delete payload.image  // ✅ correct way to remove a property
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

const updateLocalContent = (id: number, value: string) => {
    debouncedUpdate(id,  value )
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

const onDeleteContent = async (id: number) => {
    loadingDeleteIds.value.push(id)

    try {
        await axios.delete(route('grp.models.model_has_content.delete', { modelHasContent: id }))
        notify({ title: 'Deleted', text: 'Content has been deleted.', type: 'success' })

        const index = localContents.value.findIndex((item) => item.id === id)
        if (index !== -1) localContents.value.splice(index, 1)
    } catch (error: any) {
        notify({
            title: 'Error',
            text: error?.response?.data?.message ?? 'Failed to delete content.',
            type: 'error',
        })
    } finally {
        loadingDeleteIds.value = loadingDeleteIds.value.filter((itemId) => itemId !== id)
    }
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
const openDisclosureId = ref<number | null>(null)

</script>

<template>
    <div class="w-full">
        <div class="mb-6">
            <div v-if="informationContents.length === 0 && !loadingAdd"
                class="text-center text-gray-500 text-sm py-6 italic border border-dashed border-gray-300 rounded">
                <div class="py-2">No product information yet. Click the add button to insert new content.</div>
            </div>

            <div v-else class="space-y-3">
                <template v-for="content in informationContents" :key="content.id">
                    <Skeleton v-if="loadingDeleteIds.includes(content.id)" height="3rem" class="rounded-md mb-3" />
                    <div v-else class="relative">
                        <div @click="openDisclosureId = openDisclosureId === content.id ? null : content.id"
                            class="w-full sm:w-7/12 mb-1 border-b border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center cursor-pointer">
                            <EditorV2 :modelValue="content.title"
                                @update:model-value="(value) => updateLocalContent(content.id, {...content,title : value })" />
                            <FontAwesomeIcon :icon="faChevronDown"
                                class="text-sm text-gray-500 transform transition-transform duration-200"
                                :class="{ 'rotate-180': openDisclosureId === content.id }" />
                        </div>
                        <div v-show="openDisclosureId === content.id" class="text-sm text-gray-600">
                            <EditorV2 :modelValue="content.text"
                                @update:model-value="(value) => updateLocalContent(content.id, {...content,text : value })" />
                        </div>
                    </div>
                </template>
            </div>

            <!-- Add Information Button (independent hover) -->
            <button @click="addInformation"
                class="absolute top-0 right-0 opacity-0 hover:opacity-100 transition-opacity duration-300 text-sm flex items-center gap-2 text-indigo-600 hover:text-indigo-800 border border-indigo-600 hover:border-indigo-800 rounded px-3 py-2 z-10"
                title="Add Information">
                <FontAwesomeIcon :icon="faPlus" />
                <span>Add Info</span>
            </button>
        </div>

        <!-- FAQ Section -->
        <div class="mb-6 relative">
            <div class="text-sm text-gray-500 mb-1 font-semibold">Frequently Asked Questions (FAQs)</div>

            <div v-if="faqContents.length === 0 && !loadingAdd"
                class="text-center text-gray-500 text-sm py-6 italic border border-dashed border-gray-300 rounded">
                <div class="py-2">No FAQs yet. Click the add button to include some questions.</div>
            </div>

            <div v-else class="space-y-2">
                <template v-for="content in faqContents" :key="content.id">
                    <Skeleton v-if="loadingDeleteIds.includes(content.id)" height="3rem" class="rounded-md" />
                    <div v-else class="relative hover:bg-gray-50 rounded transition">
                        <div @click="openDisclosureId = openDisclosureId === content.id ? null : content.id"
                            class="w-full sm:w-7/12 mb-1 border-b border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center cursor-pointer">
                            <EditorV2 :modelValue="content.title"
                               @update:model-value="(value) => updateLocalContent(content.id, {...content,title : value })" />
                            <div class="flex items-center gap-4">
                                <button @click.stop="confirmDelete(content.id)"
                                    class="text-red-500 hover:text-red-700 transition-opacity text-xs"
                                    title="Delete Content">
                                    <FontAwesomeIcon :icon="faTrash" />
                                </button>
                                <FontAwesomeIcon :icon="faChevronDown"
                                    class="text-sm text-gray-500 transform transition-transform duration-200"
                                    :class="{ 'rotate-180': openDisclosureId === content.id }" />
                            </div>
                        </div>
                        <div v-show="openDisclosureId === content.id" class="text-sm text-gray-600">
                            <EditorV2 :modelValue="content.text"
                             @update:model-value="(value) => updateLocalContent(content.id, {...content,text : value })" />
                        </div>
                    </div>
                </template>
                <Skeleton v-if="loadingAdd" height="3rem" class="rounded-md mb-3" />
            </div>

            <!-- Add FAQ Button (independent hover) -->
            <div class="relative group/faq w-full">
                <button @click="addFAQ"
                    class="absolute w-full top-0 right-0 opacity-0 group-hover/faq:opacity-100 transition-opacity duration-300 text-sm flex items-center justify-center gap-2 text-indigo-600 hover:text-indigo-800 border border-indigo-600 hover:border-indigo-800 rounded px-3 py-2 bg-white z-10"
                    title="Add FAQ">
                    <FontAwesomeIcon :icon="faPlus" />
                    <span>Add FAQ</span>
                </button>

                <div class="">
                </div>
            </div>

        </div>
    </div>

    <ConfirmDialog>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmDialog>
</template>
