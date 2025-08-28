<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faInfoCircle } from "@fas";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Link } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import TranslationBox from '@/Components/TranslationBox.vue';
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";
import Message from "primevue/message";

library.add(faAlbumCollection);

const props = defineProps<{
    data: {
        has_webpage?: boolean;
        department: {
            name: string;
            description: string;
            image: Array<string>;
            url_master: any;
            translation_box: {
                title: string
                save_route: routeType
            }
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

console.log(props)
</script>

<template>
    <div class="px-4 pb-8 m-5">
        <!-- Master Message -->
        <Message v-if="data.department?.url_master" severity="success" closable>
            <template #icon>
                <FontAwesomeIcon :icon="faInfoCircle" />
            </template>
            <span class="ml-2">
                {{ trans("Right now you follow") }}
                <Link :href="route(data.department.url_master.name, data.department.url_master.parameters)"
                    class="underline font-bold">
                {{ trans("the master data") }}
                </Link>
            </span>
        </Message>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-4 mt-4">
            <!-- Sidebar -->
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <ProductCategoryCard :data="data.department"  />
            </div>
        </div>
    </div>

    <TranslationBox :master="data.department" :needTranslation="data.department"
        v-bind="data.translation_box" />
</template>
