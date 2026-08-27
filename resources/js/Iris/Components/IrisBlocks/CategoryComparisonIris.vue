<script setup lang="ts">
import { computed, inject, toRef } from "vue"
import CategoryComparisonRender from "@/Iris/Components/BlocksUtils/CategoryComparison/CategoryComparisonRender.vue"
import { useComparisonFamilies } from "@/Iris/Components/BlocksUtils/CategoryComparison/useComparisonFamilies"
import type { CategoryComparisonValue, ScreenType } from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

const props = defineProps<{
    fieldValue: CategoryComparisonValue
    screenType: ScreenType
    indexBlock?: number | string
    webpageData?: any
    blockData?: Record<string, any>
}>()

const injectedWebpageData = inject<any>("webpage_data", null)

const fetchParameters = computed<Record<string, string> | undefined>(() => {
    const productCategory = props.webpageData?.model_slug ?? injectedWebpageData?.model_slug

    return productCategory ? { productCategory } : undefined
})

const {
    families,
    familyOptions,
    selectedSlugs,
    numberOfComparedFamilies,
    toggleFamily,
    loading,
} = useComparisonFamilies(
    "iris.json.website.category.comparison_option",
    "iris.json.website.category.comparison_detail",
    fetchParameters,
    toRef(props, "fieldValue"),
    toRef(props, "screenType")
)
</script>

<template>
    <CategoryComparisonRender
        :fieldValue="fieldValue"
        :screenType="screenType"
        :indexBlock="indexBlock"
        :families="families"
        :familyOptions="familyOptions"
        :selectedSlugs="selectedSlugs"
        :maxComparedFamilies="numberOfComparedFamilies"
        :loading="loading"
        @toggleFamily="toggleFamily"
    />
</template>
