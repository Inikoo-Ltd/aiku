<script setup lang="ts">
import CategoryComparisonRender from "@/Iris/Components/BlocksUtils/CategoryComparison/CategoryComparisonRender.vue"
import type { CategoryComparisonValue } from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

defineProps<{
    fieldValue: CategoryComparisonValue
    screenType: "mobile" | "tablet" | "desktop"
    indexBlock?: number | string
    webpageData?: any
    blockData?: Record<string, any>
}>()
</script>

<template>
    <CategoryComparisonRender
        :fieldValue="fieldValue"
        :screenType="screenType"
        :indexBlock="indexBlock"
    />
</template>
