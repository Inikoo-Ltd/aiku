<script setup lang="ts">
import { faChevronRight } from "@far"
import LinkIris from "../LinkIris.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

const props = defineProps<{
    internalHref?: Function
    activeSubIndex: number | null
    closeSidebar: Function
    onArrowClick : Function
    nav: {
        name: string
        url?: string
        type?: "internal" | "external"
        target?: string
    }
    isWithArrowRight?: boolean
}>()

const emit = defineEmits<{
    (e: "arrow-click"): void
}>()

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
        >
            {{ nav.name }}
        </LinkIris>
        <div v-else >
            {{ nav.name }}
        </div>
        <FontAwesomeIcon v-if="isWithArrowRight" :icon="faChevronRight" fixed-width class="text-xs"  @click="emit('arrow-click')"/>
    </div>
</template>