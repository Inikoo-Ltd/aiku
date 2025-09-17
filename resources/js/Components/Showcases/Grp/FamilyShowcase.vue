<script setup lang="ts">
import { routeType } from '@/types/route';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faInfoCircle } from "@fas";
import Message from "primevue/message";
import { Link, router } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faAlbumCollection } from "@fal";
import TranslationBox from '@/Components/TranslationBox.vue';
import ProductCategoryCard from '@/Components/ProductCategoryCard.vue';
import { trans } from 'laravel-vue-i18n';

library.add(faAlbumCollection);

const props = defineProps<{
    data: {
        translation_box: {
            title: string
            save_route: routeType
        }
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
    actions?: any
}>()

const navigateTo = () => {
    let routeCurr = route().current();
    let targetRoute;
    let routeParams = route().params;
    
    switch (routeCurr) {
        case "grp.masters.master_shops.show.master_departments.show.master_families.show":
        case "grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.show" :
            targetRoute = route("grp.masters.master_shops.show.master_departments.show.master_families.edit", {
                ...routeParams,
                section: 1
            });
            break;
            
        case "grp.masters.master_shops.show.master_sub_departments.master_families.show":
            targetRoute = route("grp.masters.master_shops.show.master_sub_departments.master_families.edit", {
                ...routeParams,
                section: 1
            });
            break;

        case "grp.masters.master_shops.show.master_families.show":
            targetRoute = route("grp.masters.master_shops.show.master_families.edit", {...routeParams, section: 1})
            break;

        case "grp.org.shops.show.catalogue.families.show":
            targetRoute = route("grp.org.shops.show.catalogue.families.edit", { ...routeParams, section: 1 })
            break;
        
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show": 
            targetRoute = route("grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.edit", { ...routeParams, section: 1 })
            break;

        default:
            targetRoute = route("grp.org.shops.show.catalogue.departments.show.families.edit", {
                ...routeParams,
                section: 1
            });
            break;
    }
    router.visit(targetRoute);
}
</script>

<template>
    <div class="pb-8 m-5">
        <div class="space-y-4">
            <Message v-if="data.family?.data.url_master" severity="success" closable>
                <template #icon>
                    <FontAwesomeIcon :icon="faInfoCircle" />
                </template>
                <span class="ml-2">
                    {{ trans("Right now you follow") }}
                    <Link :href="route(data.family.data.url_master.name, data.family.data.url_master.parameters)"
                        class="underline font-bold">
                    {{ trans("the master data") }}
                    </Link>
                </span>
            </Message>
            <Message
                v-if="!data.family.data.description || !data.family.data.description_title || !data.family.data.description_extra && actions"
                severity="error" closable>
                <template #icon>
                    <FontAwesomeIcon :icon="faInfoCircle" />
                </template>
                <div class="ml-2">
                    <div class="flex gap-2 flex-wrap box-border">
                        <span v-if="!data.family.data.description_title">{{ trans("Description Title is missing")
                            }}.</span>
                        <span v-if="!data.family.data.description">{{ trans("Description is missing") }}.</span>
                        <span v-if="!data.family.data.description_extra">{{ trans("Extra description is missing")
                            }}.</span>
                    </div>
                    {{ trans("Please") }}
                    <Link
                        @click="navigateTo"
                        class="underline font-bold">
                    {{ trans("add missing description fields") }}
                    </Link>.
                </div>
            </Message>
        </div>
        <div class="px-5 grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 mb-4 ">
            <ProductCategoryCard :data="data.family.data" />
        </div>
        <!--    <TranslationBox :master="data.family.data" :needTranslation="data.family.data" v-bind="data.translation_box" /> -->
    </div>
</template>
