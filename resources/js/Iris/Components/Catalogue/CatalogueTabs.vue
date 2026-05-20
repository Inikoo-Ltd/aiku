<script setup lang="ts">
import { computed, inject, ref } from 'vue'
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
import { layoutStructure } from '@/Composables/useLayoutStructure'

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
const layoutStore = inject('layout', layoutStructure)
const isLoading = ref<string | boolean>(false)

const activeScope = computed<string>(() => {
    const scope = page.props.catalogue_scope as string | undefined
    return scope ?? 'department'
})
</script>

<template>
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex w-full gap-x-6 overflow-x-auto px-4" aria-label="Catalogue Tabs">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="route('iris.catalogue_iris', { scope: tab.key })"
                :class="[tab.key === activeScope ? 'tabNavigationActive' : 'tabNavigation']"
                class="group relative inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-2 text-left text-sm font-medium md:text-base"
                @start="() => (isLoading = tab.key)"
                @finish="() => (isLoading = false)"
            >
                <LoadingIcon v-if="isLoading === tab.key" class="h-5 w-5" />
                <FontAwesomeIcon v-else :icon="tab.icon" fixed-width class="h-5 w-5" />
                <span>{{ tab.label }}</span>
            </Link>
        </nav>
    </div>
</template>

<style lang="scss" scoped>
.tabNavigation {
    @apply transition-all duration-75;
    filter: saturate(0);
    border-bottom: v-bind("`2px solid transparent`");
    color: v-bind("`${layoutStore.app.theme[0]}99`");

    &:hover {
        filter: saturate(0.85);
        border-bottom: v-bind("`2px solid ${layoutStore.app.theme[0]}AA`");
        color: v-bind("`${layoutStore.app.theme[0]}AA`");
    }
}

.tabNavigationActive {
    border-bottom: v-bind("`2px solid ${layoutStore.app.theme[0]}`");
    color: v-bind("layoutStore.app.theme[0]");
}
</style>
