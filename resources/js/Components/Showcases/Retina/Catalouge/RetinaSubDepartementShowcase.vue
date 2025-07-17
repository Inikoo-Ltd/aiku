<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage } from "@far";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Image from "@/Components/Image.vue";
library.add(faAlbumCollection);

const props = defineProps<{
    data: {
        has_webpage ?: boolean;
        department: {
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



</script>

<template>
    <div class="px-4 pb-8 m-5">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-4 mt-4">
            <!-- Sidebar -->
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                    <div class="bg-white rounded-lg shadow mb-4 overflow-hidden">
                        <Image v-if="data?.department?.image" :src="data?.department?.image" imageCover
                               class="w-full h-40 object-cover rounded-t-lg" />
                        <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
                            <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
                        </div>
                    </div>

                    <div class="border-t pt-4 space-y-4 text-sm text-gray-700">
                        <div class="font-medium">{{ data?.department?.name || "No label" }}</div>
                        <div class="text-gray-400">
                            {{ data?.department?.description || "No description" }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
