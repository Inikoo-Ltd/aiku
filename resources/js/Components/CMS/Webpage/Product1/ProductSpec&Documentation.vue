<script setup lang="ts">
const props = defineProps<{
    product: {
        specifications: {
            gross_weight?: number
            net_weight?: number
            barcode?: number
            origin?: string
            dimensions?: [number, number]
            materials?: string
        }
    }
}>()
</script>

<template>
    <div class="w-full sm:w-7/12 border border-gray-300">
        <div v-if="product?.specifications?.origin" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">Origin</div>
            <div class="p-2 text-sm">{{ product.specifications.origin }}</div>
        </div>

        <div v-if="product?.specifications?.net_weight" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">Net Weight</div>
            <div class="p-2 text-sm">{{ product.specifications.net_weight }} g</div>
        </div>

        <div v-if="product?.specifications?.gross_weight" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">Shipping Weight</div>
            <div class="p-2 text-sm">{{ product.specifications.gross_weight }} g</div>
        </div>

        <div v-if="product?.specifications?.dimensions?.length" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">Product Dimensions</div>
            <div class="p-2 text-sm">{{ product.specifications.dimensions[0] }} x {{ product.specifications.dimensions[1] }} x {{ product.specifications.dimensions[2] }} cm</div>
        </div>

        <div v-if="product?.specifications?.materials" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">Materials/Ingredients</div>
            <div class="p-2 text-sm">{{ product.specifications.materials }}</div>
        </div>

        <div v-if="product?.specifications?.barcode" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">Barcode</div>
            <div class="p-2 text-sm">{{ product.specifications.barcode }}</div>
        </div>

        <!-- Downloadable Items -->
        <!--  <div class="grid grid-cols-2 border-b border-gray-300 bg-gray-200">
                <div class="p-2 font-medium text-sm">MSDS</div>
                <div class="p-2 text-sm text-blue-600 underline cursor-pointer">download</div>
            </div>
            <div class="grid grid-cols-2 border-b border-gray-300 bg-gray-200">
                <div class="p-2 font-medium text-sm">MSDS</div>
                <div class="p-2 text-sm text-blue-600 underline cursor-pointer">download</div>
            </div>
            <div class="grid grid-cols-2 border-b border-gray-300 bg-gray-200">
                <div class="p-2 font-medium text-sm">Materials/Ingredients</div>
                <div class="p-2 text-sm text-blue-600 underline cursor-pointer">download</div>
            </div>
            <div class="grid grid-cols-2 bg-gray-200">
                <div class="p-2 font-medium text-sm">Barcode</div>
                <div class="p-2 text-sm text-blue-600 underline cursor-pointer">download</div>
            </div> -->
    </div>
</template>
