<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faInfoCircle } from "@fas";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Link, router } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import TranslationBox from '@/Components/TranslationBox.vue';
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";
import Message from "primevue/message";
import ReviewContent from "@/Components/ReviewContent.vue";

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
            description_title: string
            description_extra: string
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


const navigateTo = () => {
    let routeCurr = route().current();
    let targetRoute;
    let routeParams = route().params;
    
    switch (routeCurr) {
        case "grp.masters.master_shops.show.master_departments.show":
            targetRoute = route("grp.masters.master_shops.show.master_departments.edit", {
                ...routeParams,
                section: 1
            });
            break;
        default:
            targetRoute = route("grp.org.shops.show.catalogue.departments.edit", {
                ...routeParams,
                section: 1
            });
            break;
    }
    router.visit(targetRoute);
}
</script>

<template>
    <div class="px-4 pb-8 m-5">
        <div class="space-y-4">
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
            <Message
                v-if="!data.department.description || !data.department.description_title || !data.department.description_extra"
                severity="error" closable>
                <template #icon>
                    <FontAwesomeIcon :icon="faInfoCircle" />
                </template>
                <div class="ml-2">
                    <div class="flex gap-2 flex-wrap box-border">
                        <span v-if="!data.department.description_title">{{ trans("Description Title is missing")
                            }}.</span>
                        <span v-if="!data.department.description">{{ trans("Description is missing") }}.</span>
                        <span v-if="!data.department.description_extra">{{ trans("Extra description is missing")
                            }}.</span>
                    </div>
                    {{ trans("Please") }}
                    <Link @click="navigateTo()" class="underline font-bold cursor-pointer">
                    {{ trans("add missing description fields") }}
                    </Link>.
                </div>
            </Message>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-4 mt-4">
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <ProductCategoryCard :data="data.department" />
            </div>
            <div class="col-span-1 md:col-span-1 lg:col-span-4"></div>
            <div class="col-span-1 md:col-span-1 lg:col-span-2"> 
                <ReviewContent :data="data.department" />
            </div>
        </div>
    </div>
</template>
