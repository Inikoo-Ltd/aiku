<script setup lang='ts'>
import Button from '@/Components/Elements/Buttons/Button.vue'
    
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLongArrowAltDown } from '@fal'
import { faStar, faCircle, faHandPointer } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
library.add(faLongArrowAltDown, faStar, faCircle, faHandPointer)

const props = defineProps<{
    product: {
        image: string
        title: string
        code: string
        rrp: string
        stock: number
        original_price: {
            price: string
            rrp: string
        }
        discount: {
            percentage: number
            price: string
            rrp: string
            isApplied: boolean
        }
    }
}>()
</script>

<template>
    <div class="grid relative border border-gray-300 rounded-md pb-4 ">
        <FontAwesomeIcon
            icon='fas fa-circle'
            class='absolute top-2 right-2'
            :class="[product.stock ? 'text-green-500 animate-pulse' : 'text-red-500']"
            v-tooltip="product.stock ? 'Ready stock' : 'Out of stock'"
            fixed-width
            aria-hidden='true'
        />
        <div>
            <img :src="product.image" alt="" class="h-auto w-full rounded-t-full">
            <div class="place-self-start mx-4 mb-2 xl:mb-4">
                <div class="min-h-12 mb-1 border border-gray-300 rounded-sm text-gray-600 font-semibold py-2 px-2 text-center flex items-center justify-center">
                    {{ product.title }}
                </div>
                <div class="flex justify-between text-xxs text-gray-500 mb-1">
                    <div>{{ product.code }}</div>
                    <div>{{ product.rrp}}</div>
                </div>
                <div class="grid grid-cols-2 xl:grid-cols-3 gap-x-2 mb-2 xl:mb-0">
                    <div class="text-gray-600 text-sm">Price</div>
                    <div class="col-span-2 text-xs flex items-center justify-between text-gray-400">
                        <div class="line-through">{{ product.original_price.price }}</div>
                        <div class="line-through text-right">({{ product.original_price.rrp}})</div>
                    </div>
                </div>
            
                <div class="grid grid-cols-2 xl:grid-cols-3 gap-x-2">
                    <div class="">
                        <div class="rounded-sm flex items-center text-sm w-fit px-1"
                        :class="[product.discount.isApplied ? 'border border-green-500 bg-green-50 text-green-500' : 'border border-gray-500 bg-gray-200 text-gray-500']"
                    >
                        <FontAwesomeIcon :icon="product.discount.isApplied ? 'fas fa-star' : 'fas fa-star-half-alt'" class='' fixed-width aria-hidden='true' />
                        <div class="flex items-center">
                            <FontAwesomeIcon icon='fal fa-long-arrow-alt-down' size="sm" class='' fixed-width aria-hidden='true' />
                            <span class="">{{ product.discount.percentage }}%</span>
                        </div>
                    </div>
                    </div>
                    <div class="col-span-2 text-sm flex items-center justify-between text-gray-500">
                        <div class="text-orange-500">{{ product.discount.price }}</div>
                        <div class="text-orange-500 text-right">({{ product.discount.rrp }})</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-4 flex flex-col xl:flex-row gap-y-2 gap-x-1.5 self-end">
            <div class="w-full xl:w-20">
                <PureInputNumber class="py-0" />
            </div>
            <Button label="Order now" icon="fas fa-hand-pointer" type="black" full />
        </div>

    </div>
</template>