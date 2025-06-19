<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { computed, ref } from "vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'


const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    },
    data: {},
    index?: {}
    sales?: {}
    routes: {
        families_route: routeType
        submit_route: routeType
    }
    is_orphan_products?: boolean
}>()
const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const locale = inject('locale', aikuLocaleStructure)

const component = computed(() => {
    const components: any = {
        index: TableProducts,
        sales: TableProducts,
    }

    return components[currentTab.value]
})

const selectedProductsId = ref<{[key: string]: boolean}>({})
const compSelectedProductsId = computed(() => Object.keys(selectedProductsId.value).filter(key => selectedProductsId.value[key]))
const isOpenModalAddToFamily = ref(false)
const selectedFamilyId = ref(null)
const isLoadingButton = ref(false)
const onSubmitToFamily = () => {
    isLoadingButton.value = true

    const selectedProductsIdToSubmit = compSelectedProductsId.value
    console.log('ewqewq', selectedProductsIdToSubmit)

    router.post(
        route(props.routes.submit_route?.name, {
            ...props.routes.submit_route?.parameters,
            family: selectedFamilyId.value,
        }),
        {
            products: selectedProductsIdToSubmit,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isOpenModalAddToFamily.value = false
                selectedFamilyId.value = null
                selectedProductsId.value = {}
                notify({
                    title: trans("Success"),
                    text: selectedProductsIdToSubmit.length + ' ' + trans("Products added to Family successfully."),
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
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                v-if="is_orphan_products"
                @click="() => isOpenModalAddToFamily = true"
                type="tertiary"
                icon="fas fa-plus"
                label="Add to Family"
                :disabled="compSelectedProductsId.length < 1"
                v-tooltip="compSelectedProductsId.length < 1 ? trans('Select at least one product') : ''"
            />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    
    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="props[currentTab]"
        :isCheckboxProducts="is_orphan_products"
        @selectedRow="(productsId: {}) => (console.log('qqqqqq', productsId), selectedProductsId = productsId)"
    />
    

    <Modal
        v-if="is_orphan_products"
        :isOpen="isOpenModalAddToFamily"
        @onClose="isOpenModalAddToFamily = false"
        width="w-full max-w-[500px]"
    >
        <div class="text-center font-semibold text-lg mb-4">
            {{ trans("Select Family to add the products to:") }}
        </div>

        <div class="mb-4">
            <PureMultiselectInfiniteScroll
                v-model="selectedFamilyId"
                :fetchRoute="props.routes.families_route"
                :placeholder="trans('Select Family')"
                valueProp="id"
                xoptionsList="(options) => dataFamilyList = options"
            >
                <template #singlelabel="{ value }">
                    <div class="w-full text-left pl-4">{{ value.name }} <span class="text-sm text-gray-400">({{ locale.number(value.number_current_products) }} {{ trans("products") }})</span></div>
                </template>
                
                <template #option="{ option, isSelected, isPointed }">
                    <div class="">{{ option.name }} <span class="text-sm text-gray-400">({{ locale.number(option.number_current_products) }} {{ trans("products") }})</span></div>
                </template>
            </PureMultiselectInfiniteScroll>
        </div>

        <Button
            @click="() => onSubmitToFamily()"
            label="Submit"
            :loading="isLoadingButton"
            full
        />
    </Modal>
</template>

