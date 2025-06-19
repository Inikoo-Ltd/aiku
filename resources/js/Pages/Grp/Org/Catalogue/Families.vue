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
        families_route: routeType
        submit_route: routeType
    }
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

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
const onSubmitToDepartment = () => {
    isLoadingButton.value = true

    const selectedFamiliesIdToSubmit = compSelectedFamiliesId.value
    console.log('ewqewq', selectedFamiliesIdToSubmit)

    router.post(
        route(props.routes.submit_route?.name, {
            ...props.routes.submit_route?.parameters,
            family: selectedDepartmentId.value,
        }),
        {
            products: selectedFamiliesIdToSubmit,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isOpenModalAddToDepartment.value = false
                selectedDepartmentId.value = null
                selectedFamiliesId.value = {}
                notify({
                    title: trans("Success"),
                    text: selectedFamiliesIdToSubmit.length + ' ' + trans("Products added to Family successfully."),
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

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                @click="() => isOpenModalAddToDepartment = true"
                type="tertiary"
                icon="fas fa-plus"
                :label="trans('Add to Department')"
                :disabled="compSelectedFamiliesId.length < 1"
                v-tooltip="compSelectedFamiliesId.length < 1 ? trans('Select at least one family') : ''"
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
        :isCheckBox="true"
        @selectedRow="(productsId: {}) => (selectedFamiliesId = productsId)"
    />
    
    <Modal :isOpen="isOpenModalAddToDepartment" @onClose="isOpenModalAddToDepartment = false" width="w-full max-w-[500px]">
        <div class="text-center font-semibold text-lg mb-4">
            {{ trans("Select Family to add the products to:") }}
        </div>

        <div class="mb-4">
            <PureMultiselectInfiniteScroll
                v-model="selectedDepartmentId"
                :fetchRoute="props.routes.families_route"
                :placeholder="trans('Select Family')"
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
            full
        />
    </Modal>
</template>
