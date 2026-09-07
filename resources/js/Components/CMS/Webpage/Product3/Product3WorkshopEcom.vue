<script setup lang="ts">
import ProductIris3Ecom from "@/Iris/Components/IrisBlocks/Product/Ecom/ProductIris3Ecom.vue"

type TemplateType = "webpage" | "template"

const props = withDefaults(
    defineProps<{
        modelValue: any
        webpageData?: any
        blockData?: object
        templateEdit?: TemplateType
        indexBlock?: number
        screenType: "mobile" | "tablet" | "desktop"
        currency?: { code: string; name: string }
        videoSetup: { url: string }
        validImages: object
        product?: any
        customerData?: any
        listProducts?: any[]
        isLoadingFavourite?: boolean
        isLoadingRemindBackInStock?: boolean
    }>(),
    {
        templateEdit: "webpage",
        indexBlock: 0
    }
)

const emits = defineEmits<{
    (e: "setFavorite", value: any): void
    (e: "unsetFavorite", value: any): void
    (e: "setBackInStock", value: any): void
    (e: "unsetBackInStock", value: any): void
    (e: "selectProduct", value: any): void
}>()
</script>

<template>
    <ProductIris3Ecom
        :fieldValue="modelValue"
        :webpageData
        :blockData
        :screenType
        :indexBlock
        :validImages
        :videoSetup
        :product="product ?? modelValue.product"
        :customerData
        :listProducts="listProducts ?? []"
        :isLoadingFavourite="isLoadingFavourite ?? false"
        :isLoadingRemindBackInStock="isLoadingRemindBackInStock ?? false"
        @setFavorite="value => emits('setFavorite', value)"
        @unsetFavorite="value => emits('unsetFavorite', value)"
        @setBackInStock="value => emits('setBackInStock', value)"
        @unsetBackInStock="value => emits('unsetBackInStock', value)"
        @selectProduct="value => emits('selectProduct', value)"
    />
</template>
