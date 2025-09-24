<script setup lang="ts">
import { faUnlink, faThLarge, faBars, faSeedling, faCheck, faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from '@/types/route'
import { ref, provide } from 'vue'
import { Image as ImageTS } from '@/types/Image'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from "laravel-vue-i18n"
import ProductCategoryCard from "../ProductCategoryCard.vue"
import { Message } from "primevue"
import { Link, router } from "@inertiajs/vue3";
import MasterNavigation from "../Navigation/MasterNavigation.vue"
import FormCreateMasterFamily from "../Master/FormCreateMasterFamily.vue"
import ReviewContent from "../ReviewContent.vue"

library.add(faUnlink, faThLarge, faBars, faSeedling, faCheck)

const props = withDefaults(
    defineProps<{
        data: {
            translation_box: {
                title: string
                save_route: routeType
            }
            subDepartment: {
                slug: string
                image_id: ImageTS | string | null
                code: string
                name: string
                state: string
                created_at: string
                updated_at: string
                description: string
                description_title: string
                description_extra: string
            }

            routes: {
                detach_family: routeType,
                attach_collections_route: routeType,
                detach_collections_route: routeType
            }
            collections: {
                data: {
                    id: number
                    name: string
                    description?: string
                    image?: ImageTS[]
                }[]
            },
            routeList: {
                collectionRoute: string,
                collections_route: string
            },
            has_wepage?: boolean
            storeFamilyRoute: any
            shopsData: any
        },
        isMaster: boolean
    }>(), {
        isMaster: false,
    }
)

const isModalOpen = ref(false)
provide('isModalOpen', isModalOpen)

const navigateTo = () => {
    let routeCurr = route().current();
    let targetRoute;
    let routeParams = route().params;
    
    switch (routeCurr) {
        case "grp.masters.master_shops.show.master_departments.show.master_sub_departments.show":
            targetRoute = route("grp.masters.master_shops.show.master_departments.show.master_sub_departments.edit", {
                ...routeParams,
                section: 1
            });
            break;
        case "grp.masters.master_shops.show.master_sub_departments.show":
            targetRoute = route("grp.masters.master_shops.show.master_sub_departments.edit", {
                ...routeParams,
                section: 1
            });
            break;
        default:
            targetRoute = route("grp.org.shops.show.catalogue.departments.show.sub_departments.edit", {
                ...routeParams,
                section: 1
            });
            break;
    }
    router.visit(targetRoute);
}

const showDialog = ref<boolean>(false)

const openFamilyModal = () => {
    showDialog.value = true
}
</script>

<template>
    <div class="px-4 pb-8 m-5">
        <!-- Master Message -->
        <div class="space-y-4">
            <Message v-if="data.subDepartment?.url_master" severity="success" closable>
                <template #icon>
                    <FontAwesomeIcon :icon="faInfoCircle" />
                </template>
                <span class="ml-2">
                    {{ trans("Right now you follow") }}
                    <Link :href="route(data.subDepartment.url_master.name, data.subDepartment.url_master.parameters)"
                        class="underline font-bold">
                    {{ trans("the master data") }}
                    </Link>
                </span>
            </Message>
            <Message
                v-if="!data.subDepartment.description || !data.subDepartment.description_title || !data.subDepartment.description_extra"
                severity="error" closable>
                <template #icon>
                    <FontAwesomeIcon :icon="faInfoCircle" />
                </template>
                <div class="ml-2">
                    <div class="flex gap-2 flex-wrap box-border">
                        <span v-if="!data.subDepartment.description_title">{{ trans("Description Title is missing")
                            }}.</span>
                        <span v-if="!data.subDepartment.description">{{ trans("Description is missing") }}.</span>
                        <span v-if="!data.subDepartment.description_extra">{{ trans("Extra description is missing")
                            }}.</span>
                    </div>
                    {{ trans("Please") }}
                    <Link @click="navigateTo()" class="underline font-bold">
                    {{ trans("add missing description fields") }}
                    </Link>.
                </div>
            </Message>
        </div>

         <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-4 mt-4">
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                  <ProductCategoryCard :data="data.subDepartment" />
            </div>
            <div  class="md:col-start-7 md:col-end-9">
                <MasterNavigation v-if="isMaster"
                    sub-department-route="grp.masters.master_shops.show.master_departments.show.master_sub_departments.create"
                    :families-event="openFamilyModal" isAddFamilies />
                <ReviewContent v-else  :data="data.subDepartment"  />
            </div>
        </div>
        
    </div>
    <FormCreateMasterFamily :showDialog="showDialog" :storeProductRoute="data.storeFamilyRoute"
        @update:show-dialog="(value) => showDialog = value" :shopsData="data.shopsData" />
</template>
