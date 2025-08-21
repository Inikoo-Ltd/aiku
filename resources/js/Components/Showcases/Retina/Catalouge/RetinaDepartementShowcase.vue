<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage } from "@far";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";

import Image from "@/Components/Image.vue";
import { trans } from "laravel-vue-i18n"
import CopyButton from "@/Components/Utils/CopyButton.vue"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"

library.add(faAlbumCollection);

const props = defineProps<{
    data: {
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

const routeAPI = window.location.origin + `/${props.data?.family?.data?.slug}/data-feed.csv`
</script>

<template>
    <div class="px-4 pb-8 m-5">
        <div class="py-2 w-fit">
            <div>
                API Url (download all products in this Department)
                <InformationIcon
                    :information="trans('Can be use to integrate with 3rd party app')"
                />
                :
            </div>
            
            <div xhref="props.data.url" target="_blank" xv-tooltip="trans('Go To Website')"
                class="xhover:bg-gray-50 ring-1 ring-gray-300 rounded overflow-hidden flex text-xxs md:text-base text-gray-500">
                <div v-tooltip="trans('Copy url')" class="flex items-center">
                    <CopyButton
                        :text="routeAPI"
                        class="text-3xl px-3 py-1.5"
                    />
                </div>

                <div class="bg-gray-200 py-2 px-2 w-full">
                    {{ routeAPI }}
                </div>
            </div>
        </div>

        <!-- <pre>{{ data.family.data.slug }}</pre> -->

        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-8 gap-4 mt-4">
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
