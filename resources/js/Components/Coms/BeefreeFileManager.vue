<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { formatBytes } from "@/Composables/useUploadLimits"
import { useFormatTime } from "@/Composables/useFormatTime"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFolder, faFolderOpen, faFile, faDownload, faHome } from "@fal"

library.add(faFolder, faFolderOpen, faFile, faDownload, faHome)

interface BeefreeItem {
    name: string
    path: string
    "mime-type": string
    size: number
    "last-modified": number
    "public-url"?: string
    thumbnail?: string
}

const props = defineProps<{
    data: { shop: string }
}>()

const currentPath = ref("")
const items = ref<BeefreeItem[]>([])
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

const isDirectory = (item: BeefreeItem) => item["mime-type"] === "application/directory"

const sortedItems = computed(() =>
    [...items.value].sort((a, b) => Number(isDirectory(b)) - Number(isDirectory(a)) || a.name.localeCompare(b.name))
)

const pathSegments = computed(() => currentPath.value.split("/").filter(Boolean))

const formatLastModified = (timestamp: number) => useFormatTime(new Date(timestamp < 1e12 ? timestamp * 1000 : timestamp))

const loadFiles = async (path: string) => {
    isLoading.value = true
    errorMessage.value = null
    try {
        const response = await axios.get(route("grp.json.shop.beefree_files.index", { shop: props.data.shop, path }))
        items.value = response.data.items ?? []
        currentPath.value = path
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || ctrans("Failed to load Beefree files")
    } finally {
        isLoading.value = false
    }
}

const openSegment = (index: number) => loadFiles(pathSegments.value.slice(0, index + 1).join("/"))

const downloadUrl = (item: BeefreeItem) => route("grp.json.shop.beefree_files.download", { shop: props.data.shop, path: item.path })

onMounted(() => loadFiles(""))
</script>

<template>
    <div class="px-4 py-4">
        <div class="flex items-center gap-1 text-sm mb-3">
            <button type="button" class="text-gray-600 hover:text-gray-900" @click="loadFiles('')">
                <FontAwesomeIcon icon="fal fa-home" fixed-width aria-hidden="true" />
                {{ ctrans("Root") }}
            </button>
            <template v-for="(segment, index) in pathSegments" :key="index">
                <span class="text-gray-400">/</span>
                <button type="button" class="text-gray-600 hover:text-gray-900" @click="openSegment(index)">{{ segment }}</button>
            </template>
            <LoadingIcon v-if="isLoading" class="ml-2" />
        </div>

        <div v-if="errorMessage" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ errorMessage }}
        </div>

        <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-3 py-2 font-medium">{{ ctrans("Name") }}</th>
                    <th class="px-3 py-2 font-medium">{{ ctrans("Type") }}</th>
                    <th class="px-3 py-2 font-medium text-right">{{ ctrans("Size") }}</th>
                    <th class="px-3 py-2 font-medium">{{ ctrans("Last modified") }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="item in sortedItems" :key="item.path" class="hover:bg-gray-50">
                    <td class="px-3 py-2">
                        <button v-if="isDirectory(item)" type="button" class="flex items-center gap-2 hover:underline" @click="loadFiles(item.path)">
                            <FontAwesomeIcon icon="fal fa-folder" fixed-width class="text-amber-500" aria-hidden="true" />
                            {{ item.name }}
                        </button>
                        <a v-else :href="item['public-url']" target="_blank" rel="noopener" class="flex items-center gap-2 hover:underline">
                            <img v-if="item.thumbnail" :src="item.thumbnail" :alt="item.name" class="h-8 w-8 rounded object-cover" loading="lazy" />
                            <FontAwesomeIcon v-else icon="fal fa-file" fixed-width class="text-gray-400" aria-hidden="true" />
                            {{ item.name }}
                        </a>
                    </td>
                    <td class="px-3 py-2 text-gray-500">{{ isDirectory(item) ? ctrans("Folder") : item["mime-type"] }}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ isDirectory(item) ? "" : formatBytes(item.size) }}</td>
                    <td class="px-3 py-2 text-gray-500">{{ item["last-modified"] ? formatLastModified(item["last-modified"]) : "" }}</td>
                    <td class="px-3 py-2 text-right">
                        <a v-if="!isDirectory(item)" :href="downloadUrl(item)" class="inline-flex items-center gap-1 rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-100">
                            <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                            {{ ctrans("Download") }}
                        </a>
                    </td>
                </tr>
                <tr v-if="!isLoading && !sortedItems.length">
                    <td colspan="5" class="px-3 py-6 text-center text-gray-500">{{ ctrans("No files") }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
