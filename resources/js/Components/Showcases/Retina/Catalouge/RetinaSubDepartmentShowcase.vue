<script setup lang="ts">
import { faAlbumCollection, faGameConsoleHandheld } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";

import BoxApiUrl from "./BoxApiUrl.vue"

library.add(faAlbumCollection);

const props = defineProps<{
    data: {
        subDepartment: {
            data: {
                name: string;
                description: string;
                image: Array<string>;
                url_master: any;
            };
        };
        routeList: {
            collectionRoute: any;
            collections_route: any;
        };
        routes: {
            attach_collections_route: any;
            detach_collections_route: any;
        };
        collections: {
            data: Array<{
                id: number;
                name: string;
                description: string;
                image: Array<string>;
            }>;
        };
    };
}>();


const routeAPI = window.location.origin + `/${props.data?.subDepartment?.slug}/data-feed.csv`
</script>

<template>
    <div class="p-6">
        <!-- Box: API URL -->
        <BoxApiUrl
            :routeApi="routeAPI"
        />


        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4 mt-4">
            <!-- Sidebar -->
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <ProductCategoryCard :data="data.subDepartment" />
            </div>
        </div>
    </div>
</template>
