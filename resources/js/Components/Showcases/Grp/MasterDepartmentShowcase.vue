<script setup lang="ts">
import { ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faInfoCircle } from "@fas";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Link, router } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";
import Message from "primevue/message";
import MasterNavigation from "@/Components/Navigation/MasterNavigation.vue";
import FormCreateMasterFamily from "@/Components/Master/FormCreateMasterFamily.vue";
import SalesAnalyticsCompact from '@/Components/Product/SalesAnalyticsCompact.vue';
import ProductCategoryStats from '@/Components/Product/ProductCategoryStats.vue';
import { routeType } from "@/types/route"

library.add(faAlbumCollection);
const props = defineProps<{
    data: {
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
        storeFamilyRoute: any
        shopsData: any
    };
    salesData?: object;
}>();

const navigateTo = () => {
    const routeParams = route().params;
    router.visit(route("grp.masters.master_shops.show.master_departments.edit", {
        ...routeParams,
        section: 1
    }));
}

const showDialog = ref<boolean>(false)

const openFamilyModal = () => {
    showDialog.value = true
}
</script>

<template>    
    <div class="px-4 pb-8 m-5">
        <div class="space-y-4">

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
            <div class="col-span-1 md:col-span-2 lg:col-span-4">
                <!-- Spacing / Content area -->
            </div>
            <div class="col-span-1 md:col-span-3 lg:col-span-2 space-y-4">
                <!-- Sales Analytics Compact -->
                <SalesAnalyticsCompact v-if="salesData" :salesData="salesData" />

                <!-- Product State Stats -->
                <ProductCategoryStats v-if="data.department.stats" :stats="data.department.stats" />

                <!-- Master Navigation -->                 
                <MasterNavigation
                    sub-department-route="grp.masters.master_shops.show.master_departments.show.master_sub_departments.create"
                    :families-event="openFamilyModal" is-add-both />
            </div>
        </div>
    </div>
    <FormCreateMasterFamily :showDialog="showDialog" :storeProductRoute="data.storeFamilyRoute"
        @update:show-dialog="(value) => showDialog = value" :shopsData="data.shopsData" />
</template>
