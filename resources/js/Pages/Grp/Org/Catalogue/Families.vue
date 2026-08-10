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
import { PageHeadingTypes } from "@/types/PageHeading"
import { computed, ref } from 'vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSeedling, faPenAlt } from "@fal"
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { routeType } from '@/types/route'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ProductsSelector from '@/Components/Dropshipping/ProductsSelector.vue'
import { useEchoGrpPersonal } from '@/Stores/echo-grp-personal'
import Dialog from "primevue/dialog"
import { Checkbox } from 'primevue'
import { watch } from 'vue'
import axios from 'axios'

const screenType = inject('screenType', ref('desktop'))
library.add(faSeedling, faPenAlt)

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
    need_review?: {}
    missing_gr?: {}
    routes: {
        departments_route: routeType
        submit_route: routeType
    }
    is_orphan_families?: boolean
    shops_do_not_have_family?: {}[]
    master_product_category_id?: number
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const isOpenModalAddToShop = ref(false)
const locale = inject('locale', aikuLocaleStructure)

const component = computed(() => {
    const components: any = {
        index: TableFamilies,
        sales: TableFamilies,
        need_review: TableFamilies,
        missing_gr: TableFamilies
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
            isOpenModalAddToShop.value = false
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}

const showDialog = ref(false);

const selectedShops = ref([]);

const allShopSelected = computed({
    get: () => props.shops_do_not_have_family?.length && Object.keys(selectedShops.value).length === Object.keys(props.shops_do_not_have_family)?.length,
    set: (value: boolean) => {
        selectedShops.value = value ? (props.shops_do_not_have_family ?? []).map(shop => shop.id) : []
    }
})

const echoPersonal = useEchoGrpPersonal()

const cloneProgress = computed(() => props.master_product_category_id
    ? echoPersonal.cloneFamilyProgress[props.master_product_category_id] ?? null
    : null
)

const isCloningFromMaster = computed(() => !!cloneProgress.value && !cloneProgress.value.isFinished)

watch(() => cloneProgress.value?.isFinished, (isFinished) => {
    if (!isFinished || !props.master_product_category_id) return

    const masterFamilyId = props.master_product_category_id
    showDialog.value = false
    selectedShops.value = []

    notify({
        title: trans("Success"),
        text: trans("Family and its products have been added to the selected shops."),
        type: "success",
    })

    router.reload({ only: ['shops_do_not_have_family', 'pageHead', currentTab.value] })

    setTimeout(() => echoPersonal.clearCloneFamilyProgress(masterFamilyId), 4000)
})

const submitCloneFromMaster = async () => {
    if (!props.master_product_category_id || isCloningFromMaster.value) return

    echoPersonal.startCloneFamilyProgress(props.master_product_category_id, props.title)

    try {
        await axios.patch(route('grp.models.master_product_category.clone-to-child-shops', {
            masterProductCategory: props.master_product_category_id
        }), {
            shop_ids: selectedShops.value
        })
    } catch (error: any) {
        echoPersonal.clearCloneFamilyProgress(props.master_product_category_id)
        notify({
            title: trans("Something went wrong."),
            text: error?.response?.data?.message || trans("Failed to add family to the selected shops, please try again."),
            type: "error",
        })
    }
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
                @click="() => isOpenModalAddToShop = true"
                type="tertiary"
                icon="fas fa-plus"
                :label="trans('Attach families')"
            />
        </template>
        <template #button-assign="{ action }">
            <Button 
                v-tooltip="action.disabled ? ctrans('Family already exists on all shops under this master shop') : ''" 
                :disabled="action.disabled"
                :icon="action.icon" 
                :label="action.label" 
                @click="()=> {showDialog = true}" 
                :style="action.style"
            />
            <Dialog
                v-model:visible="showDialog" 
                modal 
                :closable="true" 
                :dismissableMask="screenType === 'desktop'" 
                :style="{ width: '40vw', 'max-height': '40vw' }" 
                :contentClass="'!pb-0 mb-2'"
            >
                <template #header>
                    <div class="grid">
                        <div class="text-xl font-bold">
                            {{ ctrans('Add to Other Shops') }}
                        </div>
                        <div class="pt-2 my-auto text-xs">
                            <span class="text-red-500">
                                *
                            </span>
                            {{ ctrans("This would also add the products under this family to the selected shops") }}
                        </div>
                    </div>
                </template>
                <div class="h-full overflow-y-auto overflow-x-none" style="scrollbar-width: thin;">
                    <div class="border-x border-b border-gray-400">
                        <div
                            class="flex flex-1 gap-x-2 mb-2 border-y border-gray-400 bg-gray-200 top-0 sticky z-50"
                        >
                            <div class="border-r border-gray-400 px-2 py-2.5  flex">
                                <Checkbox
                                    v-model="allShopSelected"
                                    :binary="true"
                                    :class="'my-auto'"
                                />
                            </div>
                            <div class="flex">
                                <div class="px-2 font-semibold my-auto">
                                    {{ ctrans('Shop Code') }}
                                    <span class="text-xs font-normal">
                                        ({{ ctrans('Only showing shops in which this family does not exists') }})
                                    </span>
                                </div>
                                <div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-for="shop in shops_do_not_have_family" 
                            class="flex flex-1 gap-x-2 border-b border-gray-200"
                        >
                            <div class="px-2 py-1.5  my-auto flex">
                                <Checkbox 
                                    v-model="selectedShops"
                                    :value="shop.id"
                                />
                            </div>
                            <div class="flex flex-1 gap-2">
                                <div class="px-2 w-32 my-auto">
                                    {{ shop.code }}
                                </div>
                                <div class="my-auto">
                                    {{ shop.name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <template #footer>
                    <div class="flex justify-end pt-2 w-full">
                        <Button
                            @click="submitCloneFromMaster()"
                            :label="ctrans('Save')"
                            :loading="isCloningFromMaster"
                            :disabled="isCloningFromMaster || !selectedShops.length"
                        />
                    </div>
                </template>
            </Dialog>
        </template>
    </PageHeading>
    <Tabs v-if="Object.keys(tabs.navigation ?? {}).length" :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

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

    <Modal v-if="true" :isOpen="isOpenModalAddToShop" @onClose="isOpenModalAddToShop = false" width="w-full max-w-6xl">
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
