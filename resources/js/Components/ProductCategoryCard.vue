<script setup lang="ts">
import { ref } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage } from "@far";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { routeType } from "@/types/route";
import Image from "@/Components/Image.vue";

library.add(faAlbumCollection);

const props = defineProps<{
    data: {
        name: string;
        description: string;
        description_title: string;
        description_extra: string;
        image: Array<string>;
        url_master: any;
        translation_box: {
            title: string;
            save_route: routeType;
        };
    };
}>();

// toggle for "read more"
const showExtra = ref(false);
</script>

<template>
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
        <div class="bg-white rounded-lg shadow mb-4 overflow-hidden">
            <Image v-if="data?.image" :src="data?.image" imageCover class="w-full h-40 object-cover rounded-t-lg" />
            <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
                <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
            </div>
        </div>

        <div class="border-t pt-4 space-y-1 text-sm text-gray-700">
            <div class="font-medium">{{ data?.name || "No label" }}</div>
            <div class="text-gray-400" v-html="data?.description_title"></div>
            <div class="text-gray-400" v-html="data?.description"></div>

            <!-- Collapsible description extra -->
            <div v-if="showExtra" class="text-gray-400" v-html="data?.description_extra"></div>

            <!-- Toggle button -->
            <button
                v-if="data?.description_extra"
                @click="showExtra = !showExtra"
                class="text-blue-500 text-xs font-medium hover:underline focus:outline-none"
            >
                {{ showExtra ? "Read less" : "Read more" }}
            </button>
        </div>
    </div>
</template>
