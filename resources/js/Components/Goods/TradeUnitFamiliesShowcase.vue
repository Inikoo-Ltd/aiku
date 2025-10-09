<script setup lang="ts">
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";

library.add(faAlbumCollection);
const props = withDefaults(defineProps<{
    data: {};
    isMaster?: boolean
}>(), {
    // Default values
    isMaster: false,
});

console.log(props)
</script>

<template>
    <div class="px-4 pb-8 m-5">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-4 mt-4">
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <ProductCategoryCard :data="data.tradeUnitFamily" />
            </div>
        </div>
    </div>
</template>
