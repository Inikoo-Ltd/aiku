<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { onMounted, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"

const emit = defineEmits<{
    pick: [url: string]
    close: []
}>()

interface Gif {
    id: string
    preview: string
    url: string
}

const query = ref("")
const gifs = ref<Gif[]>([])
const nextPage = ref<number | null>(null)
const loading = ref(false)
const currentPage = ref(1)

let debounceTimer: ReturnType<typeof setTimeout> | null = null

const fetchGifs = async (append = false) => {
    loading.value = true
    const pageToFetch = append ? nextPage.value : 1
    const { data } = await axios.get(route("grp.chat.staff.gifs.search"), {
        params: { q: query.value || undefined, page: pageToFetch },
    })
    gifs.value = append ? [...gifs.value, ...data.data] : data.data
    nextPage.value = data.next
    currentPage.value = pageToFetch || 1
    loading.value = false
}

const onSearchInput = () => {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => fetchGifs(false), 300)
}

onMounted(() => fetchGifs(false))
</script>

<template>
    <div class="w-72 h-80 bg-white border border-gray-200 rounded-lg shadow-lg flex flex-col overflow-hidden">
        <div class="p-2 border-b border-gray-200 shrink-0">
            <input
                v-model="query"
                type="text"
                class="w-full text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                :placeholder="trans('Search GIFs')"
                @input="onSearchInput"
            />
        </div>
        <div class="flex-1 overflow-y-auto p-2">
            <div v-if="!loading && !gifs.length" class="text-center text-xs text-gray-400 py-8">
                {{ trans('No GIFs') }}
            </div>
            <div v-else class="grid grid-cols-2 gap-1.5">
                <button
                    v-for="gif in gifs"
                    :key="gif.id"
                    class="rounded overflow-hidden hover:opacity-80"
                    @click="emit('pick', gif.url)"
                >
                    <img :src="gif.preview" loading="lazy" class="w-full h-20 object-cover" />
                </button>
            </div>
            <button
                v-if="nextPage"
                class="w-full mt-2 text-xs text-indigo-600 hover:underline py-1"
                @click="fetchGifs(true)"
            >
                {{ trans('Load more') }}
            </button>
        </div>
        <div class="text-xxs text-gray-400 text-right px-2 pb-1 shrink-0">
            Powered by KLIPY
        </div>
    </div>
</template>
