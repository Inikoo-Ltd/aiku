<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { capitalize } from "@/Composables/capitalize"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { SubSection } from "@/types/Navigation"
import { faPallet, faTruck, faTruckCouch, faTruckRamp } from "@fal";
import { faFolderTree, faBooks, faFolder, faCube, faAlbumCollection, faDotCircle as FarDotCircle } from "@far";
import { ref, computed } from "vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faDotCircle, faTruck, faPallet, faTruckRamp, faTruckCouch, faFolderTree, faBooks, faFolder, faCube, faAlbumCollection, FarDotCircle)

const layoutStore = useLayoutStore()

const props = defineProps<{
    subSections: SubSection[]
}>()

// Check current Route have provided routeName
const isSubSectionActive = (routeName: string) => {
    if (!routeName) return false

    return (layoutStore.currentRoute).includes(routeName)
}

const isLoading = ref<string | boolean>(false)

// Build color from theme with tailwind-safe fallback
const mainColor = computed(() => {
    const raw = layoutStore.app.theme[0] || "#3B82F6" // default to Tailwind blue-500
    return "#" + raw.replace("#", "") // remove '#' if present
})

</script>

<template>
    <nav class="flex items-center space-x-4 border-b border-gray-200 px-4 w-full">
        <template v-for="(subSection, idxSubSec) in subSections" :key="idxSubSec">
            <component :is="subSection.route?.name ? Link : 'div'"
                :href="subSection.route?.name ? route(subSection.route.name, subSection.route.parameters) : '#'" :class="[
                    'relative flex items-center gap-2 px-4 py-2 font-medium text-sm transition duration-150 ease-in-out border-b-2 rounded-t-md',
                    isSubSectionActive(subSection.root)
                        ? `text-[${mainColor}] border-[${mainColor}] bg-[${mainColor}1A]`
                        : `text-gray-600 border-transparent hover:text-[${mainColor}] hover:border-[${mainColor}] hover:bg-[${mainColor}0D]`
                ]" :title="capitalize(subSection.tooltip ?? subSection.label ?? '')"
                @start="() => isLoading = 'subSection' + idxSubSec" @finish="() => isLoading = false">
                <LoadingIcon v-if="isLoading === 'subSection' + idxSubSec" class="h-4 w-4" />
                <FontAwesomeIcon v-else-if="subSection.icon" :icon="subSection.icon" class="h-4 w-4" />
                <FontAwesomeIcon v-else icon="fas fa-dot-circle" class="h-4 w-4" />
                <span class="whitespace-nowrap">
                    {{ capitalize(subSection.label || '') }}
                </span>
            </component>
        </template>
    </nav>
</template>
