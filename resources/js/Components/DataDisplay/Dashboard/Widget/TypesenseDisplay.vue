<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSort, faSortUp, faSortDown } from '@fal'
import { computed, ref } from 'vue'

library.add(faSort, faSortUp, faSortDown)

type CollectionMetric = {
    name: string
    documents: number
    size_bytes: number
    size: string
}

const props = defineProps<{
    widget?: {
        memory: string
        total_documents: number
        collections: CollectionMetric[]
    } | null
}>()

type SortKey = 'name' | 'documents' | 'size_bytes'

const sortKey = ref<SortKey>('documents')
const sortDesc = ref(true)

const toggleSort = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDesc.value = !sortDesc.value
    } else {
        sortKey.value = key
        sortDesc.value = key !== 'name'
    }
}

const sortedCollections = computed(() => {
    const collections = [...(props.widget?.collections ?? [])]
    const key = sortKey.value
    collections.sort((a, b) => {
        const compared = key === 'name' ? a.name.localeCompare(b.name) : a[key] - b[key]
        return sortDesc.value ? -compared : compared
    })
    return collections
})

const sortIcon = (key: SortKey) => {
    if (sortKey.value !== key) return 'fal fa-sort'
    return sortDesc.value ? 'fal fa-sort-down' : 'fal fa-sort-up'
}
</script>

<template>
    <div class="bg-white rounded-lg p-4 flex flex-col shadow-sm border border-gray-300">
        <h3 class="text-lg font-semibold mb-2">{{ ctrans("Search engine") }}</h3>

        <template v-if="widget">
            <div class="flex gap-10 mb-4">
                <div>
                    <p class="text-4xl font-bold">{{ widget.total_documents.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Indexed documents") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ widget.memory }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Memory used") }}</p>
                </div>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-400 border-b border-gray-200">
                        <th class="text-left font-medium py-1.5 cursor-pointer select-none" @click="toggleSort('name')">
                            {{ ctrans("Collection") }}
                            <FontAwesomeIcon :icon="sortIcon('name')" fixed-width aria-hidden="true" />
                        </th>
                        <th class="text-right font-medium py-1.5 cursor-pointer select-none" @click="toggleSort('documents')">
                            {{ ctrans("Records") }}
                            <FontAwesomeIcon :icon="sortIcon('documents')" fixed-width aria-hidden="true" />
                        </th>
                        <th class="text-right font-medium py-1.5 cursor-pointer select-none" @click="toggleSort('size_bytes')">
                            {{ ctrans("Size (est.)") }}
                            <FontAwesomeIcon :icon="sortIcon('size_bytes')" fixed-width aria-hidden="true" />
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="collection in sortedCollections" :key="collection.name">
                        <td class="py-1.5 text-gray-600">{{ collection.name }}</td>
                        <td class="py-1.5 text-right font-medium tabular-nums">{{ collection.documents.toLocaleString() }}</td>
                        <td class="py-1.5 text-right tabular-nums text-gray-600">{{ collection.size }}</td>
                    </tr>
                </tbody>
            </table>
        </template>

        <p v-else class="text-sm text-gray-500">{{ ctrans("Search engine metrics unavailable") }}</p>
    </div>
</template>
