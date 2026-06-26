<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faSpinner, faTimes, faCheck } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { debounce } from "lodash-es"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { router } from "@inertiajs/vue3"
import { InputText } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faLink, faSpinner, faTimes, faCheck)

const props = defineProps<{
    portfolioId: number
    currentItemId: number | null
    currentItemData?: { id: number; reference: string; name: string; total_quantity: number } | null
}>()

type StoredItem = { id: number; reference: string; total_quantity: number; name: string }

const isOpen = ref(false)
const storedItems = ref<StoredItem[]>([])
const loading = ref(false)
const loadingMore = ref(false)
const searchQuery = ref('')
const updating = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)

const fetchItems = async (search: string, page = 1) => {
    if (page === 1) {
        loading.value = true
        storedItems.value = []
    } else {
        loadingMore.value = true
    }

    try {
        const params: Record<string, string | number> = { page }
        if (search) {
            params['filter[global]'] = search
        }

        const response = await axios.get(route('retina.fulfilment.storage.stored-items.index'), {
            params,
            headers: { Accept: 'application/json' },
        })

        const data = response.data.data ?? []
        storedItems.value = page === 1 ? data : [...storedItems.value, ...data]
        currentPage.value = response.data.meta?.current_page ?? page
        lastPage.value = response.data.meta?.last_page ?? 1
    } catch {
        if (page === 1) {
            storedItems.value = []
        }
    } finally {
        loading.value = false
        loadingMore.value = false
    }
}

const debFetch = debounce((search: string) => fetchItems(search, 1), 400)

watch(searchQuery, debFetch)

const close = () => {
    isOpen.value = false
    storedItems.value = []
    searchQuery.value = ''
    currentPage.value = 1
    lastPage.value = 1
}

const toggle = async () => {
    if (isOpen.value) {
        close()
        return
    }
    document.dispatchEvent(new CustomEvent('sku-picker:close-all'))
    isOpen.value = true
    await fetchItems('', 1)
}

onMounted(() => document.addEventListener('sku-picker:close-all', close))
onUnmounted(() => document.removeEventListener('sku-picker:close-all', close))

const onScroll = (event: Event) => {
    const el = event.target as HTMLElement
    const nearBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 40

    if (nearBottom && !loadingMore.value && !loading.value && currentPage.value < lastPage.value) {
        fetchItems(searchQuery.value, currentPage.value + 1)
    }
}

const currentItem = computed(() =>
    props.currentItemData ?? storedItems.value.find(item => item.id === props.currentItemId) ?? null
)
const otherItems = computed(() => storedItems.value.filter(item => item.id !== props.currentItemId))

const selectItem = async (storedItem: StoredItem) => {
    if (updating.value) {
        return
    }
    updating.value = true
    try {
        await axios.patch(route('retina.models.portfolio.update', { portfolio: props.portfolioId }), {
            item_id: storedItem.id,
            item_type: 'StoredItem',
        })
        notify({ title: trans('Success'), text: trans('SKU updated successfully'), type: 'success' })
        isOpen.value = false
        storedItems.value = []
        router.reload({ only: ['products'] })
    } catch {
        notify({ title: trans('Error'), text: trans('Failed to update SKU'), type: 'error' })
    } finally {
        updating.value = false
    }
}
</script>

<template>
    <div class="relative">
        <Button
            v-tooltip="trans('Match existing SKU')"
            @click.prevent="toggle"
            :style="isOpen ? 'primary' : 'secondary'"
            :icon="['fal', 'fa-link']"
            :label="trans('Match SKU')"
            size="xs"
            class="whitespace-nowrap" />

        <div
            v-if="isOpen"
            class="absolute right-0 top-full mt-1 z-50 w-96 bg-white border border-gray-200 rounded-lg shadow-xl p-3">
            <button
                @click="close"
                class="absolute top-2 right-2 h-6 w-6 flex items-center justify-center rounded-full bg-white border border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-colors shadow-sm">
                <FontAwesomeIcon icon="fal fa-times" class="text-xs" fixed-width aria-hidden="true" />
            </button>
            <div class="mb-2">
                <span class="text-xs font-semibold text-gray-700">{{ trans('Select SKU to match') }}</span>
            </div>

            <InputText
                v-model="searchQuery"
                :placeholder="trans('Search SKU...')"
                size="small"
                class="w-full mb-2"
                autofocus />

            <div class="max-h-52 overflow-y-auto space-y-0.5" @scroll="onScroll">
                <div v-if="loading" class="flex items-center justify-center gap-2 py-4 text-xs text-gray-400">
                    <FontAwesomeIcon icon="fal fa-spinner" class="animate-spin" fixed-width aria-hidden="true" />
                    {{ trans('Loading...') }}
                </div>
                <template v-else>
                    <template v-if="currentItem">
                        <div class="px-2 py-1 text-xs font-semibold text-blue-500 uppercase tracking-wide">
                            {{ trans('Current SKU') }}
                        </div>
                        <div
                            class="cursor-pointer rounded px-3 py-2 text-xs flex items-center gap-2 bg-blue-50 border border-blue-200 text-blue-700 mb-1"
                            :class="updating ? 'opacity-50 pointer-events-none' : ''"
                            @click="selectItem(currentItem)">
                            <span class="font-mono font-semibold shrink-0 w-36 truncate">{{ currentItem.reference }}</span>
                            <span class="truncate flex-1">{{ currentItem.name }}</span>
                            <!-- <span class="shrink-0 tabular-nums opacity-70">{{ currentItem.total_quantity ?? 0 }}</span> -->
                            <FontAwesomeIcon icon="fal fa-check" class="text-blue-500 shrink-0" fixed-width aria-hidden="true" />
                        </div>
                        <div v-if="otherItems.length" class="px-2 py-1 text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            {{ trans('All SKUs') }}
                        </div>
                    </template>

                    <div
                        v-for="storedItem in otherItems"
                        :key="storedItem.id"
                        @click="selectItem(storedItem)"
                        :class="[
                            'cursor-pointer rounded px-3 py-2 text-xs flex items-center gap-2 transition-colors hover:bg-gray-50 text-gray-700',
                            updating ? 'opacity-50 pointer-events-none' : '',
                        ]">
                        <span class="font-mono font-semibold shrink-0 w-36 truncate">{{ storedItem.reference }}</span>
                        <span class="text-gray-500 truncate flex-1">{{ storedItem.name }}</span>
                        <span class="shrink-0 text-gray-400 tabular-nums">{{ storedItem.total_quantity ?? 0 }}</span>
                    </div>

                    <div v-if="storedItems.length === 0" class="py-4 text-xs text-gray-400 text-center">
                        {{ trans('No SKUs found') }}
                    </div>
                </template>
                <div v-if="loadingMore" class="flex items-center justify-center gap-2 py-2 text-xs text-gray-400">
                    <FontAwesomeIcon icon="fal fa-spinner" class="animate-spin" fixed-width aria-hidden="true" />
                    {{ trans('Loading more...') }}
                </div>
            </div>
        </div>
    </div>
</template>
