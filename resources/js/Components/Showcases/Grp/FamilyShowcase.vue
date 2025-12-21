<script setup lang="ts">
import { routeType } from '@/types/route';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faInfoCircle } from "@fas";
import Message from "primevue/message";
import { Link, router } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faAlbumCollection } from "@fal";
import ReviewContent from '@/Components/ReviewContent.vue';
import ProductCategoryCard from '@/Components/ProductCategoryCard.vue';
import { trans } from 'laravel-vue-i18n';
import { faExternalLink } from '@far';

library.add(faAlbumCollection);

const props = withDefaults(defineProps<{
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
    isMaster?: boolean
}>(), {
    // Default values
    isMaster: false,
});

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
    <div v-if="data.webpage_url"
		class="w-full bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 px-4 py-3 mb-3 shadow-sm">
		<div class="flex items-center gap-2 text-blue-700 text-sm">
			<FontAwesomeIcon :icon="faExternalLink" class="text-blue-500" />
			<a :href="data.webpage_url" target="_blank" rel="noopener noreferrer"
				class="font-medium break-all hover:underline hover:text-blue-800 transition-colors duration-200">
				{{ data.webpage_url }}
			</a>
		</div>
	</div>
    <div class="pb-8 m-5">
        <div class="space-y-4">
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

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-4 mt-4">
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <ProductCategoryCard :data="data.family.data"  />
            </div>
            <div class="col-span-1 md:col-span-1 lg:col-span-4"></div>
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <ReviewContent v-if="!isMaster" :data="data.family.data"  />
            </div>
        </div>

    </div>
</template>
