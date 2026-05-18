<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faFolderTree,
    faFolder,
    faCube,
    faAlbumCollection,
    faDotCircle as FarDotCircle,
    faBooks,
} from '@far'

library.add(faFolderTree, faFolder, faCube, faAlbumCollection, FarDotCircle, faBooks)

type ScopeTab = {
    key: string
    label: string
    icon: any
}

const tabs: ScopeTab[] = [
    { key: 'department', label: 'Departments', icon: faFolderTree },
    { key: 'sub_department', label: 'Sub Departments', icon: FarDotCircle },
    { key: 'collection', label: 'Collections', icon: faAlbumCollection },
    { key: 'family', label: 'Families', icon: faFolder },
    { key: 'product', label: 'Products', icon: faCube },
]

const page = usePage()

const activeScope = computed<string>(() => {
    const scope = page.props.catalogue_scope as string | undefined
    return scope ?? 'department'
})
</script>

<template>
    <div class="max-w-7xl mx-auto my-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <!-- Persistent Tab Bar -->
            <nav class="flex items-center px-2 border-b border-gray-200 overflow-x-auto">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="route('iris.catalogue_iris', { scope: tab.key })"
                    :class="[
                        'relative flex items-center py-2 font-medium text-xs md:text-sm transition duration-150 ease-in-out border-b-2 rounded-t-md gap-2 px-4 whitespace-nowrap',
                        tab.key === activeScope
                            ? 'text-slate-600 border-slate-500'
                            : 'text-gray-600 border-transparent hover:text-slate-600 hover:border-slate-300'
                    ]"
                >
                    <FontAwesomeIcon :icon="tab.icon" fixed-width class="h-4 w-4" />
                    <span>{{ tab.label }}</span>
                </Link>
            </nav>

            <!-- Page Content -->
            <slot />
        </div>
    </div>
</template>
