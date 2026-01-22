<script setup lang="ts">
import { ProductResource } from '@/types/Iris/Products'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    product: ProductResource
}>()

</script>

<template>
    <div class="bg-gray-50 border border-orange-500 border-b-4 rounded-md px-2 py-1 leading-3 text-xxs">
        {{ trans("Order :itemQuantity or more to get member price", { itemQuantity: product.available_gr_offer_to_use.trigger_data.item_quantity }) }}
    </div>
</template>