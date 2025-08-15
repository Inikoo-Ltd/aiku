<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
const props = defineProps<{
    product: {
        specifications: {
            gross_weight?: number
            net_weight?: number
            barcode?: number
            origin?: string
            dimensions?: [number, number]
            ingredients?: Array<string>
        }
    }
}>()



console.log(props)
</script>

<template>
    <div class="w-full sm:w-7/12 border border-gray-300">
        <div v-if="product?.specifications?.origin" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{trans('Origin')}}</div>
            <div class="p-2 text-sm">{{ product.specifications.origin }}</div>
        </div>

        <div v-if="product?.specifications?.net_weight" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{trans('Net Weight')}}</div>
            <div class="p-2 text-sm">{{ product.specifications.net_weight }} g/{{product.specifications.unit}}</div>
        </div>

        <div v-if="product?.specifications?.gross_weight" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{trans("Shipping Weight")}}</div>
            <div class="p-2 text-sm">{{ product.specifications.gross_weight }} g</div>
        </div>

        <div v-if="product?.specifications?.dimensions" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{trans("Dimensions")}}</div>
            <div class="p-2 text-sm">{{product?.specifications?.dimensions  }}</div>
        </div>


        <div v-if="product?.specifications?.ingredients" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{trans('Materials/Ingredients')}}</div>
            <div class="p-2 text-sm"> {{ product.specifications.ingredients.join(', ') }}</div>
        </div>

        <div v-if="product?.specifications?.barcode" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{trans('Barcode')}}</div>
            <div class="p-2 text-sm">{{ product.specifications.barcode }}</div>
        </div>

          <div v-if="product?.specifications?.cpnp" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{trans('cpnp')}}</div>
            <div class="p-2 text-sm">{{ product?.specifications?.cpnp }}</div>
        </div>

        <div v-if="product?.specifications?.country_of_origin?.code" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{trans('Origin Country')}}</div>
            <div class="p-2 flex items-center gap-2">
                <img :src="'/flags/' + product.specifications.country_of_origin.code.toLowerCase() + '.png'"
                    :alt="product.specifications.country_of_origin.name"
                    :title="product.specifications.country_of_origin.name" class="h-4 w-auto inline-block" />
                <span class="text-sm">
                    {{ product.specifications.country_of_origin.name }}
                </span>
            </div>
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
