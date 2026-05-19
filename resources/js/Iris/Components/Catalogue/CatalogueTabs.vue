<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faFolderTree,
    faFolder,
    faCube,
    faAlbumCollection,
    faDotCircle,
    faBooks,
} from '@fal'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

library.add(faFolderTree, faFolder, faCube, faAlbumCollection, faDotCircle, faBooks)

type ScopeTab = {
    key: string
    label: string
    icon: any
}

const tabs: ScopeTab[] = [
    { key: 'department', label: 'Departments', icon: faFolderTree },
    { key: 'sub_department', label: 'Sub Departments', icon: faDotCircle },
    { key: 'collection', label: 'Collections', icon: faAlbumCollection },
    { key: 'family', label: 'Families', icon: faFolder },
    { key: 'product', label: 'Products', icon: faCube },
]

const page = usePage()
const isLoading = ref<string | boolean>(false)

const activeScope = computed<string>(() => {
    const scope = page.props.catalogue_scope as string | undefined
    return scope ?? 'department'
})
</script>

<template>
    <nav class="flex items-center px-2 border-b border-gray-200 overflow-x-auto">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="route('iris.catalogue_iris', { scope: tab.key })"
            :class="[
                'relative flex items-center py-2 font-medium text-sm md:text-base transition duration-150 ease-in-out border-b-2 rounded-t-md gap-2 px-4 whitespace-nowrap',
                tab.key === activeScope
                    ? 'text-black border-slate-500'
                    : 'text-gray-600 border-transparent hover:text-slate-600 hover:border-slate-300'
            ]"
            @start="() => (isLoading = tab.key)"
            @finish="() => (isLoading = false)"
        >
            <LoadingIcon v-if="isLoading === tab.key" class="h-5 w-5 text-slate-600" />
            <FontAwesomeIcon v-else :icon="tab.icon" fixed-width class="h-5 w-5" />
            <span>{{ tab.label }}</span>
        </Link>
    </nav>
</template>
