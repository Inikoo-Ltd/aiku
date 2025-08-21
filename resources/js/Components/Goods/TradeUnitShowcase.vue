<script setup lang="ts">
import EditTradeUnit from './EditTradeUnit.vue'
import { routeType } from '@/types/route'
import { TradeUnit } from '@/types/trade-unit'
import TranslationBox from '@/Components/TranslationBox.vue';

const props = defineProps<{
  data: {
    tradeUnit: TradeUnit
    brand: {}
    brand_routes: Record<string, routeType>
    tag_routes: Record<string, routeType>
    tags: {}[]
    tags_selected_id: number[]
    translation_box: {
      title: string
      save_route: routeType
  }
}}>()


</script>

<template>
  <div class="px-8 grid grid-cols-2 gap-8">
    <!-- Left: Master Content -->
    <EditTradeUnit
      :tags_selected_id="props.data.tags_selected_id"
      :brand="props.data.brand"
      :brand_routes="props.data.brand_routes"
      :tags="props.data.tags"
      :tag_routes="props.data.tag_routes"
    />
  </div>

   <TranslationBox
        v-bind="data.translation_box" 
        :master="data.tradeUnit" 
        :needTranslation="data.tradeUnit" 
        
    />
</template>
