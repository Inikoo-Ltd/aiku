<script setup lang="ts">
import { routeType } from '@/types/route';
import { library } from "@fortawesome/fontawesome-svg-core";
import { faAlbumCollection } from "@fal";
import BoxApiUrl from './BoxApiUrl.vue'
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";

library.add(faAlbumCollection);

const props = defineProps<{
  data: {
    family: {
      data: {},
    },
    routeList: {
      collectionRoute: routeType
    },
    routes: {
      detach_family: routeType
    }
  }
}>()

const routeAPI = window.location.origin + `/${props.data?.family?.data?.slug}/data-feed.csv`

</script>

<template>
  <div class="px-4 pb-8 m-5">
    <!-- Box: API URL -->
    <BoxApiUrl :routeApi="routeAPI" />

    <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4 mt-4">
      <div class="col-span-1 md:col-span-1 lg:col-span-2">
        <ProductCategoryCard :data="data.family.data" />
      </div>

    </div>
  </div>
</template>
