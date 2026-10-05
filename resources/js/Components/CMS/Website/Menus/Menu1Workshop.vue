<script setup lang="ts">
import { computed, inject, ComputedRef } from "vue"
import Menu1Iris from "@/Iris/Components/IrisBlocks/Menu1Iris.vue"
import { MenuSidebarSource } from "@/Composables/Iris/useMenu"

defineProps<{
    fieldValue: Record<string, any>
    screenType?: "mobile" | "tablet" | "desktop"
}>()

const workshopSidebar = inject<ComputedRef<{
    data?: {
        fieldValue?: Partial<MenuSidebarSource>
    }
} | null> | null>("sidebarMenu", null)

const sidebarSource = computed<MenuSidebarSource>(() => {
    const sidebarFieldValue = workshopSidebar?.value?.data?.fieldValue

    return {
        navigation: sidebarFieldValue?.navigation ?? [],
        navigation_bottom: sidebarFieldValue?.navigation_bottom ?? [],
        product_categories: sidebarFieldValue?.product_categories ?? [],
    }
})
</script>

<template>
    <Menu1Iris :fieldValue :screenType :sidebar="sidebarSource" />
</template>
