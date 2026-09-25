<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faInfoCircle } from "@fas";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Link, router } from "@inertiajs/vue3";
import { ctrans } from "@/Composables/useTrans";
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";
import Message from "primevue/message";
import ReviewContent from '@/Components/ReviewContent.vue';
import SalesAnalyticsCompact from '@/Components/Product/SalesAnalyticsCompact.vue';
import SalesAnalysisTeaser from '@/Components/SalesAnalysis/SalesAnalysisTeaser.vue';
import SalesAnalysisMovers from '@/Components/SalesAnalysis/SalesAnalysisMovers.vue';
import ProductCategoryStats from '@/Components/Product/ProductCategoryStats.vue';
import { faExternalLink } from '@far';
import { routeType } from "@/types/route"

library.add(faAlbumCollection);
const props = defineProps<{
    data: {
        webpage_url?: string;
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
            stats: any
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
    salesData?: object;
    salesAnalysisTeaser?: object;
}>();

const navigateTo = () => {
    const routeParams = route().params;
    router.visit(route("grp.org.shops.show.catalogue.departments.edit", {
        ...routeParams,
        section: 1
    }));
}
</script>

<template>    
    <div v-if="data.webpage_url"
		class="w-full bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 px-4 py-3 mb-3 shadow-sm">
		<div class="flex items-center gap-2 text-blue-700 text-sm">
			<FontAwesomeIcon :icon="faExternalLink" class="text-blue-500" fixed-width />
			<a :href="data.webpage_url" target="_blank" rel="noopener noreferrer"
				class="font-medium break-all hover:underline hover:text-blue-800 transition-colors duration-200">
				{{ data.webpage_url }}
			</a>
		</div>
	</div>
    <div class="px-4 pb-8 m-5">
        <div class="space-y-4">

            <Message
                v-if="!data.department.description || !data.department.description_title || !data.department.description_extra"
                severity="error" closable>
                <template #icon>
                    <FontAwesomeIcon :icon="faInfoCircle" fixed-width />
                </template>
                <div class="ml-2">
                    <div class="flex gap-2 flex-wrap box-border">
                        <span v-if="!data.department.description_title">{{ ctrans("Description Title is missing")
                        }}.</span>
                        <span v-if="!data.department.description">{{ ctrans("Description is missing") }}.</span>
                        <span v-if="!data.department.description_extra">{{ ctrans("Extra description is missing")
                        }}.</span>
                    </div>
                    {{ ctrans("Please") }}
                    <Link @click="navigateTo()" class="underline font-bold cursor-pointer">
                    {{ ctrans("add missing description fields") }}
                    </Link>.
                </div>
            </Message>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-4 mt-4">
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <ProductCategoryCard :data="data.department" />
            </div>
            <div class="col-span-1 md:col-span-2 lg:col-span-4">
                <SalesAnalysisTeaser :teaser="salesAnalysisTeaser" class="mb-4" />

                <div class="flex flex-col gap-4 lg:flex-row">
                    <SalesAnalyticsCompact v-if="salesData" :salesData="salesData" class="lg:max-w-[23rem]" />
                    <SalesAnalysisMovers :teaser="salesAnalysisTeaser" class="min-w-0 flex-1" />
                </div>
            </div>
            <div class="col-span-1 md:col-span-3 lg:col-span-2 space-y-4">
                <!-- Product State Stats -->
                <ProductCategoryStats v-if="data.department.stats" :stats="data.department.stats" />

                <!-- Review Content -->
                <ReviewContent :data="data.department" />
            </div>
        </div>
    </div>
</template>
