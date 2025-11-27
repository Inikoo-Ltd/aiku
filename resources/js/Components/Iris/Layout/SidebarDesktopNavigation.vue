<script setup lang="ts">
import { faChevronRight } from "@far"
import LinkIris from "../LinkIris.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ref } from "vue"

const props = defineProps<{
    internalHref?: Function
    activeSubIndex: number | null
    closeSidebar: Function
    nav: {
        name: string
        url?: string
        type?: "internal" | "external"
        target?: string
    }
    isWithArrowRight?: boolean
}>()

const isLoading = ref(false)
</script>

<template>
    <div class="p-2 px-4 flex items-center justify-between gap-x-2 " >
        <LinkIris
            v-if="nav.url"
            :href="internalHref ? internalHref(nav) : nav.url"
            class="hover:underline cursor-pointer"
            @success="() => closeSidebar()"
            :type="nav.type"
            :target="nav.target"
            @start="() => isLoading = true"
            @finish="() => isLoading = false"
        >
            {{ nav.name }}
        </LinkIris>
        <div v-else>
            {{ nav.name }}
        </div>
        <LoadingIcon v-if="isLoading" class="text-sm" />
        <FontAwesomeIcon v-else-if="isWithArrowRight" :icon="faChevronRight" fixed-width class="text-xs" />
    </div>
</template>