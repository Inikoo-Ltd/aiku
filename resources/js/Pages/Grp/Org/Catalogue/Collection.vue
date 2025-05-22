<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, ref } from 'vue'
import type { Component } from 'vue'

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import Popover from '@/Components/Popover.vue'

import CollectionsShowcase from '@/Components/Dropshipping/Catalogue/CollectionsShowcase.vue'

import TableDepartments from '@/Components/Tables/Grp/Org/Catalogue/TableDepartments.vue'
import TableCollections from '@/Components/Tables/Grp/Org/Catalogue/TableCollections.vue'
import TableProducts from '@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue'
import TableFamilies from '@/Components/Tables/Grp/Org/Catalogue/TableFamilies.vue'

import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import Button from '@/Components/Elements/Buttons/Button.vue'

import {
    faPlus
} from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(
    faPlus
);


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: {
        stats: {}
    }
    departments?: {}
    families?: {}
    products?: {}
    collections?: {}

    routes: {
        departments: {
            dataList: routeType
            submitAttach: routeType
            detach: routeType
        }
        families: {
            dataList: routeType
            submitAttach: routeType
            detach: routeType
        }
        products: {
            dataList: routeType
            submitAttach: routeType
            detach: routeType
        }
        collections: {
            dataList: routeType
            submitAttach: routeType
            detach: routeType
        }
    }
    
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = async (tabSlug: string) => {
    useTabChange(tabSlug, currentTab)
    errorMessage.value = ''
}

const component = computed(() => {
    const components: Component = {
        showcase: CollectionsShowcase,
        departments: TableDepartments,
        families: TableFamilies,
        products: TableProducts,
        collections: TableCollections,
    }

    return components[currentTab.value]
})


const isLoading = ref<string | boolean>(false)
const errorMessage = ref<string>('')

const selectedFamiliesId = ref([])
const selectedDepartmentsId = ref([])
const selectedProductsId = ref([])
const selectedCollectionsId = ref([])
const onSubmitDepartments = async (closedPopover: Function, scope: string, routeToSubmit: routeType, dataToSubmit: {}, methodSuccess: Function) => {
    isLoading.value = 'submitAttach'

    router.post(
        route(routeToSubmit.name, routeToSubmit.parameters),
        dataToSubmit,
        {
            preserveScroll: true,
            onSuccess: () => {
                closedPopover()
                notify({
                    title: trans('Succes'),
                    text: trans('Successfully attach') + ` ${scope}.`,
                    type: 'success',
                })
                methodSuccess()
            },
            onError: (errors: any) => {
                // console.log(errors)
                errorMessage.value = errors
                notify({
                    title: trans('Something went wrong.'),
                    text: trans('Failed to attach') + ` ${scope}, ` + trans('please try again') + '.',
                    type: 'error',
                })
            },
            onFinish: () => {
                isLoading.value = false
            }
        }
    )
}
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <!-- Button: Departments -->
            <Popover v-if="currentTab == 'departments'">
                <template #button="{ open }">
                    <Button
                        type="secondary"
                        label="Attach department"
                        icon="fal fa-plus"
                        :tooltip="trans('Attach department to this collections')"
                    />
                </template>
                <template #content="{ close: closed }">
                    <div class="w-[350px] px-1 pb-2">
                        <div class="text-sm px-1 my-2 block tabular-nums">{{ trans('Select department') }}: {{ selectedDepartmentsId.length }} {{ trans('selected') }} </div>
                        <div class="">
                            <PureMultiselectInfiniteScroll
                                v-model="selectedDepartmentsId"
                                :fetchRoute="routes.departments.dataList"
                                mode="multiple"
                            />

                            <p v-if="errorMessage" class="mt-2 text-sm text-red-500">
                                {{ errorMessage }}
                            </p>
                        </div>
                        
                        <div class="flex justify-end mt-3">
                            <Button
                                @click="async () => onSubmitDepartments(closed, 'department', routes.departments.submitAttach, { departments: selectedDepartmentsId }, () => selectedDepartmentsId = [])"
                                :style="'save'"
                                :loading="isLoading == 'submitAttach'"
                                :disabled="!selectedDepartmentsId.length"
                                label="Save"
                                full
                            />
                        </div>
                        

                    </div>
                </template>
            </Popover>

            <!-- Button: Families -->
            <Popover v-if="currentTab == 'families'">
                <template #button="{ open }">
                    <Button
                        type="secondary"
                        label="Attach family"
                        icon="fal fa-plus"
                        :tooltip="trans('Attach family to this collections')"
                    />
                </template>
                <template #content="{ close: closed }">
                    <div class="w-[350px] px-1 pb-2">
                        <div class="text-sm px-1 my-2 block tabular-nums">{{ trans('Select family') }}: {{ selectedFamiliesId.length }} {{ trans('selected') }} </div>
                        <div class="">
                            <PureMultiselectInfiniteScroll
                                v-model="selectedFamiliesId"
                                :fetchRoute="routes.families.dataList"
                                mode="multiple"
                            />

                            <p v-if="errorMessage" class="mt-2 text-sm text-red-500">
                                {{ errorMessage }}
                            </p>
                        </div>
                        
                        <div class="flex justify-end mt-3">
                            <Button
                                @click="async () => onSubmitDepartments(closed, 'families', routes.families.submitAttach, { families: selectedFamiliesId }, () => selectedFamiliesId = [])"
                                :style="'save'"
                                :loading="isLoading == 'submitAttach'"
                                :disabled="!selectedFamiliesId.length"
                                label="Save"
                                full
                            />
                        </div>
                    </div>
                </template>
            </Popover>

            <!-- Button: Products -->
            <Popover v-if="currentTab == 'products'">
                <template #button="{ open }">
                    <Button
                        type="secondary"
                        label="Attach products"
                        icon="fal fa-plus"
                        :tooltip="trans('Attach products to this collections')"
                    />
                </template>
                <template #content="{ close: closed }">
                    <div class="w-[350px] px-1 pb-2">
                        <div class="text-sm px-1 my-2 block tabular-nums">{{ trans('Select products') }}: {{ selectedProductsId.length }} {{ trans('selected') }} </div>
                        <div class="">
                            <PureMultiselectInfiniteScroll
                                v-model="selectedProductsId"
                                :fetchRoute="routes.products.dataList"
                                mode="multiple"
                            />

                            <p v-if="errorMessage" class="mt-2 text-sm text-red-500">
                                {{ errorMessage }}
                            </p>
                        </div>
                        
                        <div class="flex justify-end mt-3">
                            <Button
                                @click="async () => onSubmitDepartments(closed, 'products', routes.families.submitAttach, { products: selectedProductsId }, () => selectedProductsId = [])"
                                :style="'save'"
                                :loading="isLoading == 'submitAttach'"
                                :disabled="!selectedProductsId.length"
                                label="Save"
                                full
                            />
                        </div>
                    </div>
                </template>
            </Popover>
            
            <!-- Button: collections -->
            <Popover v-if="currentTab == 'collections'">
                <template #button="{ open }">
                    <Button
                        type="secondary"
                        label="Attach collections"
                        icon="fal fa-plus"
                        :tooltip="trans('Attach other collections to this collections')"
                    />
                </template>
                <template #content="{ close: closed }">
                    <div class="w-[350px] px-1 pb-2">
                        <div class="text-sm px-1 my-2 block tabular-nums">{{ trans('Select collections') }}: {{ selectedCollectionsId.length }} {{ trans('selected') }} </div>
                        <div class="">
                            <PureMultiselectInfiniteScroll
                                v-model="selectedCollectionsId"
                                :fetchRoute="routes.collections.dataList"
                                mode="multiple"
                            />

                            <p v-if="errorMessage" class="mt-2 text-sm text-red-500">
                                {{ errorMessage }}
                            </p>
                        </div>
                        
                        <div class="flex justify-end mt-3">
                            <Button
                                @click="async () => onSubmitDepartments(closed, 'collections', routes.collections.submitAttach, { collections: selectedCollectionsId }, () => selectedCollectionsId = [])"
                                :style="'save'"
                                :loading="isLoading == 'submitAttach'"
                                :disabled="!selectedCollectionsId.length"
                                label="Save"
                                full
                            />
                        </div>
                    </div>
                </template>
            </Popover>
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    
    <component
        :is="component"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
        :routes="props.routes[currentTab]"
    />
</template>