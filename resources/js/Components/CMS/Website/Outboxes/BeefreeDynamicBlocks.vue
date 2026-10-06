<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { ctrans } from '@/Composables/useTrans'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

interface DynamicBlock {
    id: number
    name: string
    compiled_layout: string
    workshop_url: string
    shop_name: string
}

const props = defineProps<{
    shopSlug?: string
}>()

const isModalOpen = ref(false)
const searchQuery = ref('')
const isFromOtherShops = ref(false)
const dynamicBlocks = ref<DynamicBlock[]>([])
const isLoading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)

let resolveSelection: ((value: { name: string, value: string }) => void) | null = null
let rejectSelection: (() => void) | null = null
let searchDebounceTimeout: ReturnType<typeof setTimeout> | null = null

const fetchDynamicBlocks = async (page: number = 1) => {
    if (!props.shopSlug) {
        dynamicBlocks.value = []
        return
    }

    isLoading.value = true
    try {
        const response = await axios.get(
            route('grp.json.shop.dynamic_block_email_templates', { shop: props.shopSlug }),
            { params: { search: searchQuery.value.trim(), other_shops: isFromOtherShops.value ? 1 : 0, per_page: 10, page } }
        )
        dynamicBlocks.value = response.data.data || []
        currentPage.value = response.data.meta?.current_page || 1
        lastPage.value = response.data.meta?.last_page || 1
    } catch (error) {
        console.error('Dynamic blocks fetch error:', error)
        dynamicBlocks.value = []
    } finally {
        isLoading.value = false
    }
}

const onSearchInput = () => {
    if (searchDebounceTimeout) {
        clearTimeout(searchDebounceTimeout)
    }
    searchDebounceTimeout = setTimeout(() => fetchDynamicBlocks(1), 300)
}

const setSource = (fromOtherShops: boolean) => {
    isFromOtherShops.value = fromOtherShops
    fetchDynamicBlocks(1)
}

const extractBodyHtml = (compiledLayout: string): string => {
    return new DOMParser().parseFromString(compiledLayout, 'text/html').body.innerHTML.trim()
}

const resetModal = () => {
    resolveSelection = null
    rejectSelection = null
    isModalOpen.value = false
    searchQuery.value = ''
    isFromOtherShops.value = false
    dynamicBlocks.value = []
}

const selectDynamicBlock = (dynamicBlock: DynamicBlock) => {
    resolveSelection?.({
        name: dynamicBlock.name,
        value: extractBodyHtml(dynamicBlock.compiled_layout),
    })
    resetModal()
}

const closeModal = () => {
    rejectSelection?.()
    resetModal()
}

const openModal = () => {
    return new Promise((resolve, reject) => {
        resolveSelection = resolve
        rejectSelection = reject
        searchQuery.value = ''
        isModalOpen.value = true
        fetchDynamicBlocks(1)
    })
}

defineExpose({
    openModal
})
</script>

<template>
    <Modal :isOpen="isModalOpen" @onClose="closeModal" width="w-full max-w-4xl" :closeButton="true">
        <div class="p-4">
            <h3 class="text-lg font-semibold mb-4">{{ ctrans('Dynamic Blocks') }}</h3>

            <div class="mb-3 flex gap-2">
                <Button :key="`this-shop-${isFromOtherShops}`" :type="isFromOtherShops ? 'tertiary' : 'primary'" :label="ctrans('This shop')" size="sm"
                    @click="setSource(false)" />
                <Button :key="`other-shops-${isFromOtherShops}`" :type="isFromOtherShops ? 'yellow' : 'tertiary'" :label="ctrans('Other shops')" size="sm"
                    @click="setSource(true)" />
            </div>

            <div class="mb-4">
                <PureInput v-model="searchQuery" :placeholder="ctrans('Search dynamic block name...')"
                    @input="onSearchInput" :autofocus="true" />
            </div>

            <div v-if="isLoading" class="flex justify-center py-8">
                <LoadingIcon class="text-3xl" />
            </div>

            <div v-else-if="dynamicBlocks.length > 0" class="max-h-[28rem] overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-for="dynamicBlock in dynamicBlocks" :key="dynamicBlock.id"
                        class="flex flex-col gap-3 p-3 hover:bg-gray-50 border border-gray-200 rounded-lg transition-colors">
                        <div class="h-48 overflow-hidden rounded bg-gray-100 pointer-events-none">
                            <iframe :srcdoc="dynamicBlock.compiled_layout" sandbox="" loading="lazy"
                                class="w-[200%] h-96 origin-top-left scale-50 border-0" />
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 truncate">{{ dynamicBlock.name }}</div>
                                <div v-if="isFromOtherShops" class="text-xs text-gray-500 truncate">
                                    {{ dynamicBlock.shop_name }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a :href="dynamicBlock.workshop_url" target="_blank" rel="noopener"
                                    class="primaryLink text-sm">
                                    {{ ctrans('Edit') }}
                                </a>
                                <Button type="secondary" :label="ctrans('Select')" size="sm"
                                    @click.stop="selectDynamicBlock(dynamicBlock)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-8 text-gray-500">
                {{ searchQuery.trim()
                    ? ctrans('No dynamic blocks found matching :search', { search: searchQuery })
                    : isFromOtherShops
                        ? ctrans('No dynamic blocks in other shops yet.')
                        : ctrans('No dynamic blocks yet. Create a template in Comms → Templates with Dynamic block turned on.') }}
            </div>

            <div v-if="lastPage > 1" class="mt-4 flex items-center justify-between border-t pt-4">
                <div class="text-sm text-gray-600">
                    {{ ctrans('Page :current of :last', { current: currentPage, last: lastPage }) }}
                </div>
                <div class="flex items-center gap-2">
                    <Button type="secondary" :label="ctrans('Previous')" size="sm"
                        :disabled="currentPage === 1 || isLoading" @click="fetchDynamicBlocks(currentPage - 1)" />
                    <Button type="secondary" :label="ctrans('Next')" size="sm"
                        :disabled="currentPage === lastPage || isLoading" @click="fetchDynamicBlocks(currentPage + 1)" />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="closeModal" />
            </div>
        </div>
    </Modal>
</template>
