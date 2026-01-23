<script setup lang="ts">
import { ProductResource } from '@/types/Iris/Products'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    offer?: {
        category_qty_trigger: number
    }
}>()

</script>

<template>
    <div class="bg-gray-50 border border-orange-500 border-b-4 rounded-md px-2 py-1 leading-3 text-xxs">
        <span v-if="offer?.category_qty_trigger">{{ trans("Order :itemQuantity or more outers to get member price", { itemQuantity: offer.category_qty_trigger }) }}</span>
        <span v-else>{{ trans("Order some quantities get member price") }}</span>
    </div>
</template>