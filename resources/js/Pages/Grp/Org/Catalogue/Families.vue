<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableFamilies from "@/Components/Tables/Grp/Org/Catalogue/TableFamilies.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { computed, ref } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSeedling } from "@fal"
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { routeType } from '@/types/route'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ProductsSelector from '@/Components/Dropshipping/ProductsSelector.vue'
library.add(faSeedling)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    },
    data: {}
    index?: {}
    sales?: {}
    routes: {
        departments_route: routeType
        submit_route: routeType
    }
    is_orphan_families?: boolean
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const isOpenModalPortfolios = ref(false)
const locale = inject('locale', aikuLocaleStructure)

const component = computed(() => {
    const components: any = {
        index: TableFamilies,
        sales: TableFamilies,
    }

    return components[currentTab.value]
})

const selectedFamiliesId = ref<{[key: string]: boolean}>({})
const compSelectedFamiliesId = computed(() => Object.keys(selectedFamiliesId.value).filter(key => selectedFamiliesId.value[key]))
const isOpenModalAddToDepartment = ref(false)
const selectedDepartmentId = ref(null)
const isLoadingButton = ref(false)
const isLoadingSubmit = ref(false)
const onSubmitToDepartment = () => {
    isLoadingButton.value = true

    const selectedFamiliesIdToSubmit = compSelectedFamiliesId.value

    router.post(
        route(props.routes.submit_route?.name, {
            ...props.routes.submit_route?.parameters,
            department: selectedDepartmentId.value,
        }),
        {
            families: selectedFamiliesIdToSubmit,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isOpenModalAddToDepartment.value = false
                selectedDepartmentId.value = null
                selectedFamiliesId.value = {}
                notify({
                    title: trans("Success"),
                    text: selectedFamiliesIdToSubmit.length + ' ' + trans("Families added to Department successfully."),
                    type: "success",
                })
            },
            onError: (errors) => {
                console.error(errors)
                notify({
                    title: 'Something went wrong.',
                    text: 'Failed to add Family, please try again.',
                    type: 'error',
                })
            },
            onFinish: () => {
                isLoadingButton.value = false
            }
        }
    )
}

const onSubmitAddItem = async (idProduct: number[]) => {
    router.post(route(props.routes.attach.name, props.routes.attach.parameters ),
    {
        families_id: idProduct
    },
    {
        onBefore: () => isLoadingSubmit.value = true,
        onError: (error) => {
            notify({
                title: "Something went wrong.",
                text: error.products || undefined,
                type: "error"
            })
        },
        onSuccess: () => {
            router.reload({only: ['data']})
            notify({
                title: trans("Success!"),
                text: trans("Successfully added families"),
                type: "success"
            })
            isOpenModalPortfolios.value = false
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}


</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                v-if="is_orphan_families"
                @click="() => isOpenModalAddToDepartment = true"
                type="tertiary"
                icon="fas fa-plus"
                :label="trans('Add to Department')"
                :disabled="compSelectedFamiliesId.length < 1"
                v-tooltip="compSelectedFamiliesId.length < 1 ? trans('Select at least one family') : ''"
            />

            <Button
                v-if="routes?.fetch_families"
                @click="() => isOpenModalPortfolios = true"
                type="tertiary"
                icon="fas fa-plus"
                :label="trans('Attach families')"
            />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <!-- <pre>{{ selectedFamiliesId }}</pre> -->
    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="props[currentTab]"
        :routes="routes"
        :isCheckBox="is_orphan_families"
        @selectedRow="(productsId: {}) => (selectedFamiliesId = productsId)"
    />
    
    <Modal v-if="is_orphan_families" :isOpen="isOpenModalAddToDepartment" @onClose="isOpenModalAddToDepartment = false" width="w-full max-w-[500px]">
        <div class="text-center font-semibold text-lg mb-4">
            {{ trans("Select Family to add the products to:") }}
        </div>

        <div class="mb-4">
            <PureMultiselectInfiniteScroll
                v-model="selectedDepartmentId"
                :fetchRoute="props.routes.departments_route"
                :placeholder="trans('Select Department')"
                valueProp="id"
                xoptionsList="(options) => dataFamilyList = options"
            >
                <template #singlelabel="{ value }">
                    <div class="w-full text-left pl-4">{{ value.name }} <span class="text-sm text-gray-400">({{ locale.number(value.number_current_families) }} {{ trans("families") }})</span></div>
                </template>
                
                <template #option="{ option, isSelected, isPointed }">
                    <div class="">{{ option.name }} <span class="text-sm text-gray-400">({{ locale.number(option.number_current_families) }} {{ trans("families") }})</span></div>
                </template>
            </PureMultiselectInfiniteScroll>
        </div>

        <Button
            @click="() => onSubmitToDepartment()"
            label="Submit"
            :loading="isLoadingButton"
            full
        />
    </Modal>

    <Modal v-if="true" :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" width="w-full max-w-6xl">
        <ProductsSelector
            v-if="routes?.fetch_families"
            :headLabel="trans('Add Family to portfolios')"
            :route-fetch="routes.fetch_families"
            :isLoadingSubmit
            @submit="(products: {}[]) => onSubmitAddItem(products.map((product: any) => product.id))"
        >
            <template #product="{ item }">
                <Image v-if="item.image" :src="item.image" class="w-16 h-16 overflow-hidden" imageCover :alt="item.name" />
                <div class="flex flex-col justify-between">
                    <div class="w-fit" xclick="() => selectProduct(item)">
                        <div v-tooltip="trans('Name')" class="w-fit font-semibold leading-none mb-1">{{ item.name || 'no name' }}</div>
                        <div v-if="!item.no_code" v-tooltip="trans('Code')" class="w-fit text-xs text-gray-400 italic">{{ item.code || 'no code' }}</div>
                        <div v-if="item.reference" v-tooltip="trans('Reference')" class="w-fit text-xs text-gray-400 italic">{{ item.reference || 'no reference' }}</div>
                        <div v-if="item.gross_weight" v-tooltip="trans('Weight')" class="w-fit text-xs text-gray-400 italic">{{ item.gross_weight }}</div>
                    </div>
                    <div v-tooltip="trans('Price')" class="w-fit text-xs text-gray-x500">
                        {{ locale?.number(item.number_current_products || 0) }} {{ trans("products") }}
                    </div>
                </div>
            </template>
        </ProductsSelector>
    </Modal>
</template>
