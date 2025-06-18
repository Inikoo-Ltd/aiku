<script setup lang="ts">
import { routeType } from '@/types/route'

import EditTradeUnit from './EditTradeUnit.vue'

const props = defineProps<{
    data: {
        brand: {}
        brand_routes: {
            index_brand: routeType
            store_brand: routeType
            update_brand: routeType
            delete_brand: routeType
            attach_brand: routeType
            detach_brand: routeType
        }
        tag_routes: {
            index_tag: routeType
            store_tag: routeType
            update_tag: routeType
            delete_tag: routeType
            attach_tag: routeType
            detach_tag: routeType
        }
        tags: {}[]
        tags_selected_id: number[]
    }
}>()

</script>

<template>
    <div class="px-8 grid grid-cols-2">
        <EditTradeUnit
            :tags_selected_id="props.data.tags_selected_id"
            :brand="props.data.brand"
            :brand_routes="props.data.brand_routes"
            :tags="props.data.tags"
            :tag_routes="props.data.tag_routes"
        />
    </div>
</template>