<script setup lang="ts">
import { onMounted, ref, inject, watch } from "vue";
import axios from "axios"
import BeefreeSDK from '@beefree.io/sdk'
import { routeType } from "@/types/route";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

const props = withDefaults(defineProps<{
    updateRoute: routeType;
    imagesUploadRoute: routeType
    snapshot: any
    unpublished_layout: any
    mergeTags: Array<any>
    mergeContents: Array<any> | null
    organisationSlug: string
    shopSlug?: string
}>(), {});

const locale = inject('locale', aikuLocaleStructure)
const showBee = ref(false)
const isLoading = ref(false)
const beeInstance = ref<BeefreeSDK | null>(null)

// Product search dialog state
const productSearchModalOpen = ref(false)
const productSearchQuery = ref('')
const productSearchResults = ref<Array<any>>([])
const productSearchLoading = ref(false)
let productSearchResolve: ((value: any) => void) | null = null
let productSearchReject: (() => void) | null = null
let searchDebounceTimeout: ReturnType<typeof setTimeout> | null = null


const emits = defineEmits<{
    (e: 'onSave', value: string | number): void
    (e: 'sendTest', value: string | number): void
    (e: 'saveTemplate', value: string | number): void
    (e: 'autoSave', value: string | number): void
    (e: 'ready', value: boolean): void
}>()

const initializeBeefree = async () => {
    try {
        emits('ready', false)
        // Check if organisation prop exists
        if (!props.organisationSlug) {
            console.error('Organisation parameter is required')
            showBee.value = false
            emits('ready', false)
            return
        }
        isLoading.value = true
        showBee.value = true
        // Get authentication token
        const response = await axios.post(
            route("grp.json.beefree.authenticate", {
                organisation: props.organisationSlug,
            }),
            { uid: 'CmsUserName' }
        )

        // Check if response is successful
        if (!response.data || response.status !== 200) {
            throw new Error('Authentication failed: Invalid response from server')
        }

        const token = response.data

        // Configure Beefree with all options
        const beeConfig = {
            uid: 'CmsUserName',
            container: 'beefree-container',
            language: 'en-US',
            loadingSpinnerDisableOnDialog: true,
            saveRows: true,
            disableBaseColors: true,
            disableColorHistory: true,
            templateLanguageAutoTranslation: true,
            mergeTags: props.mergeTags,
            mergeContents: props.mergeContents || [],
            customAttributes: {
                attributes: [
                    {
                        key: "data-segment",
                        value: [
                            "1.2",
                            "1.3"
                        ],
                        target: "link"
                    },
                    {
                        key: "class",
                        target: "tag"
                    },
                    {
                        key: "class",
                        target: "link"
                    }
                ]
            },
            autosave: 20,
            contentDialog: {
                specialLinks: props.shopSlug ? [{
                    label: 'Search Product by SKU',
                    handler: (resolve: (value: any) => void, reject: () => void) => {
                        productSearchResolve = resolve
                        productSearchReject = reject
                        productSearchModalOpen.value = true
                        productSearchQuery.value = ''
                        productSearchResults.value = []
                    }
                }] : undefined,

                mergeContents: {
                    label: 'Custom text for merge contents',
                    handler: function (resolve: (value: any) => void, reject: () => void) {
                        productSearchResolve = resolve
                        productSearchReject = reject
                        productSearchModalOpen.value = true
                        productSearchQuery.value = ''
                        productSearchResults.value = []
                    }
                },
            },
            onSend: (htmlFile: string, jsonFile: string) => {
                emits('sendTest', { jsonFile, htmlFile })
            },
            onSave: (pageJson: string, pageHtml: string, ampHtml: string | null, templateVersion: number, language: string | null) => {
                emits('onSave', { jsonFile: pageJson, htmlFile: pageHtml })
            },
            onSaveAsTemplate: (jsonFile: string, htmlFile: string) => {
                emits('saveTemplate', { jsonFile, htmlFile })
            },
            onAutoSave: (jsonFile: string) => {
                emits('autoSave', jsonFile)
            },
            onError: (error: unknown) => {
                console.error('Beefree Error:', error)
            }
        }

        // Initialize Beefree SDK
        beeInstance.value = new BeefreeSDK(token)

        // Start with template if available
        const template = props.unpublished_layout ? JSON.stringify(props.unpublished_layout) : (props.snapshot?.layout ? JSON.stringify(props.snapshot.layout) : {})
        await beeInstance.value.start(beeConfig, template)

        isLoading.value = false
        emits('ready', true)
    } catch (error) {
        isLoading.value = false
        showBee.value = false
        emits('ready', false)
        console.error('Initialization error:', error)
    }
}

onMounted(() => {
    initializeBeefree()
})

watch(
    () => props.snapshot,
    async (newSnapshot) => {
        if (!beeInstance.value || !newSnapshot) return

        const template = JSON.stringify(newSnapshot)
        await beeInstance.value.load(template)
    },
    { deep: true }
)


defineExpose({
    beeInstance,
})

// Product search functions
const searchProducts = async () => {
    if (!props.shopSlug || !productSearchQuery.value.trim()) {
        productSearchResults.value = []
        return
    }

    productSearchLoading.value = true
    try {
        const response = await axios.get(
            route('grp.json.shop.products_beefree_search', {
                shop: props.shopSlug,
            }), {
            params: {
                search: productSearchQuery.value.trim(),
                per_page: 20
            }
        }
        )
        productSearchResults.value = response.data.data || []
    } catch (error) {
        console.error('Product search error:', error)
        productSearchResults.value = []
    } finally {
        productSearchLoading.value = false
    }
}

const onSearchInput = () => {
    if (searchDebounceTimeout) {
        clearTimeout(searchDebounceTimeout)
    }
    searchDebounceTimeout = setTimeout(() => {
        searchProducts()
    }, 300)
}

const selectProduct = (product: any) => {
    if (productSearchResolve) {
        const productLink = {
            type: 'product_link',
            label: product.name || product.code,
            link: product.url || `/shop/${props.shopSlug}/product/${product.slug || product.id}`,
            value: {
                id: product.id,
                code: product.code,
                name: product.name,
                description: product.description || '',
                image: product.web_images?.[0] || null,
                price: product.price,
                url: product.url || `/shop/${props.shopSlug}/product/${product.slug || product.id}`
            }
        }
        productSearchResolve(productLink)
        productSearchResolve = null
        productSearchReject = null
    }
    productSearchModalOpen.value = false
    productSearchQuery.value = ''
    productSearchResults.value = []
}

const closeProductSearchModal = () => {
    if (productSearchReject) {
        productSearchReject()
        productSearchReject = null
        productSearchResolve = null
    }
    productSearchModalOpen.value = false
    productSearchQuery.value = ''
    productSearchResults.value = []
}

</script>

<template>
    <div v-if="showBee" class="beefree-wrapper">
        <!-- Loading Animation -->
        <div v-if="isLoading" class="loading-overlay">
            <div class="loading-spinner">
                <LoadingIcon class="text-7xl" />
                <p class="loading-text">Loading Workshop Editor...</p>
            </div>
        </div>

        <!-- Beefree Container -->
        <div id="beefree-container" ref="containerRef" class="editor-container" :class="{ 'loading': isLoading }">
        </div>
    </div>

    <div v-else>
        <EmptyState :data="{
            title: 'You Need Register Your Beefree api key',
            action: {
                tooltip: 'Setting',
                type: 'create',
                route: {
                    name: 'grp.sysadmin.settings.edit'
                },
                icon: ['fas', 'user-cog']
            }
        }" />
    </div>

    <!-- Product Search Modal -->
    <Modal :isOpen="productSearchModalOpen" @onClose="closeProductSearchModal" width="w-full max-w-2xl"
        :closeButton="true">
        <div class="p-4">
            <h3 class="text-lg font-semibold mb-4">Search Product by SKU</h3>

            <!-- Search Input -->
            <div class="mb-4">
                <PureInput v-model="productSearchQuery" placeholder="Type SKU or product code..." @input="onSearchInput"
                    :autofocus="true" />
            </div>

            <!-- Loading State -->
            <div v-if="productSearchLoading" class="flex justify-center py-4">
                <LoadingIcon class="text-3xl" />
            </div>

            <!-- Results List -->
            <div v-else-if="productSearchResults.length > 0" class="max-h-96 overflow-y-auto">
                <div v-for="product in productSearchResults" :key="product.id" @click="selectProduct(product)"
                    class="flex items-center gap-4 p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0 transition-colors">
                    <!-- Product Image -->
                    <div class="w-16 h-16 flex-shrink-0 bg-gray-100 rounded overflow-hidden">
                        <img v-if="product.web_images && product.web_images.length > 0" :src="product.web_images[0]"
                            :alt="product.name" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                            No Image
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 truncate">{{ product.name }}</div>
                        <div class="text-sm text-gray-500">SKU: {{ product.code }}</div>
                        <div v-if="product.price" class="text-sm text-gray-600 mt-1">
                            Price: {{ product.price }}
                        </div>
                    </div>

                    <!-- Select Button -->
                    <Button type="secondary" label="Select" size="sm" @click.stop="selectProduct(product)" />
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="productSearchQuery.trim() && !productSearchLoading" class="text-center py-8 text-gray-500">
                No products found matching "{{ productSearchQuery }}"
            </div>

            <!-- Initial State -->
            <div v-else class="text-center py-8 text-gray-400">
                Type a SKU or product code to search
            </div>

            <!-- Cancel Button -->
            <div class="mt-4 flex justify-end">
                <Button type="tertiary" label="Cancel" @click="closeProductSearchModal" />
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.beefree-wrapper {
    position: relative;
    height: calc(100vh - 177px);
}

.editor-container {
    height: 100%;
    transition: opacity 0.3s ease;
}

.editor-container.loading {
    opacity: 0.3;
    pointer-events: none;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(255, 255, 255, 0.95);
    z-index: 1000;
}

.loading-spinner {
    text-align: center;
}

.loading-text {
    font-size: 16px;
    color: #555;
    font-weight: 500;
    margin: 0;
}

.top-bar {
    display: none;
}
</style>
