<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faBullhorn,
    faCameraRetro,
    faCube,
    faFolder,
    faMoneyBillWave,
    faProjectDiagram,
    faTag,
    faUser,
    faBrowser,
    faPlus, faMinus,
    faUpload,
} from "@fal"
import { faExclamationTriangle } from "@fas"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, inject, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableMailshots from "@/Components/Tables/TableMailshots.vue"
import { capitalize } from "@/Composables/capitalize"
import FamilyShowcase from "@/Components/Showcases/Grp/FamilyShowcase.vue"
import { Message } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { routeType } from "@/types/route";
import FormCreateMasterProduct from "@/Components/FormCreateMasterProduct.vue"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import TableFamilies from "@/Components/Tables/Grp/Org/Catalogue/TableFamilies.vue"
import ImagesManagement from "@/Components/Goods/ImagesManagement.vue"
import Breadcrumb from 'primevue/breadcrumb'
import { create } from "lodash"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"

library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faTag,
    faBullhorn,
    faProjectDiagram,
    faUser,
    faMoneyBillWave,
    faBrowser, faExclamationTriangle
)


const props = defineProps<{
    title: string
    pageHead: object
    tabs: {
        current: string
        navigation: object
    }
    storeProductRoute : routeType
    customers: object
    mailshots: object
    showcase: object
    details: object
    history?: object;
    families?: object   
    is_orphan?: boolean
    currency?:Object
    url_master?:routeType
    shopsData? :any
    masterProductCategory?:number
    images?:object
    mini_breadcrumbs?: any[]
}>()
const layout = inject("layout")
const currentTab = ref(props.tabs.current)
const isModalUploadOpen = ref(false)

const handleTabUpdate = (tabSlug: string) => {
    useTabChange(tabSlug, currentTab)
}

const component = computed(() => {
    const components = {
        showcase: FamilyShowcase,
        mailshots: TableMailshots,
        families: TableFamilies,
        customers: TableCustomers,
        details: ModelDetails,
        history: TableHistories,
        images : ImagesManagement
    }
    return components[currentTab.value] ?? ModelDetails
})

const showDialog = ref(false);


</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #button-master-product="{ action }">
            <Button :icon="action.icon" :label="action.label" @click="showDialog = true" :style="action.style" />
        </template>

        <template #afterTitle2>
           <div class="whitespace-nowrap">
            <Link v-if="url_master"  :href="route(url_master.name,url_master.parameters)"  v-tooltip="'Go to Master'" class="mr-1"  :class="'opacity-70 hover:opacity-100'">
                <FontAwesomeIcon
                    :icon="faOctopusDeploy"
                    color="#4B0082"
                />
            </Link>
            </div>
        </template>

        <template #other>
			<Button
                v-if="layout?.app?.environment === 'local'"
				@click="() => (isModalUploadOpen = true)"
				:style="create"
				:icon="faUpload"
				v-tooltip="'upload excel'" 
                label="Upload Excel"
            />
		</template>
    </PageHeading>

    <Message v-if="is_orphan" severity="warn" class="m-4 mb-2">
        <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
        {{ trans("This family is not assigned to any department. You can add it in edit section.") }}
    </Message>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

     <div v-if="mini_breadcrumbs.length != 0" class="bg-white  px-4 py-2  w-full  border-gray-200 border-b overflow-x-auto">
     <Breadcrumb :model="mini_breadcrumbs">
            <template #item="{ item, index }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <!-- Breadcrumb link or text -->
                    <span v-if="!item.to">
                        {{ item.label || '-' }}
                    </span>
                    <component v-else :is="item.to ? Link : 'span'" :href="route(item.to.name,item.to.parameters)" v-tooltip="item.tooltip"
                        :title="item.title" class="flex items-center gap-2 text-sm transition-colors duration-150"
                        :class="item.to
                            ? 'text-gray-500'
                            : 'text-gray-500 cursor-default'">
                        <FontAwesomeIcon :icon="item.icon" class="w-4 h-4" />
                        <span class="">{{ item.label || '-' }}</span>
                    </component>
                </div>
            </template>
        </Breadcrumb>
    </div>

    <component :is="component" :data="props[currentTab]" :tab="currentTab" is-master />

    <FormCreateMasterProduct 
        :showDialog="showDialog" 
        :storeProductRoute="storeProductRoute" 
        @update:show-dialog="(value) => showDialog = value"
        :master-currency="currency"
        :shopsData="shopsData"
        :masterProductCategory="masterProductCategory"
    />


    <UploadExcel
		v-model="isModalUploadOpen"
		scope="Pallet delivery"
		:title="{
			label: 'Upload your excel file',
			information: `The list of column file:`,
		}"
		progressDescription="Adding Pallet Deliveries"
		:additionalDataToSend="undefined" />

</template>

<style scoped>
/* Remove default breadcrumb styles */
:deep(.p-breadcrumb) {
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
}
</style>
