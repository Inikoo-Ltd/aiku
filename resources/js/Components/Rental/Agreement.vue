<script setup lang="ts">
import { ref, onBeforeMount } from 'vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import RentalTable from '@/Components/Rental/Table.vue'
import RentalBluprint from './Bluprint/rental.js'
import ServicesBluprint from './Bluprint/services.js'
import PhysicalGoodsBluprint from './Bluprint/physicalGoods.js'
import { inject } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { cloneDeep } from 'lodash-es'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { routeType } from '@/types/route.js'
import Popover from '@/Components/Popover.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'


const layout = inject('layout', layoutStructure)

const props = withDefaults(defineProps<{
    form: any
    tabs?: Array
    fieldName: string
    options?: any
    reset?: boolean
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
        value?: Array,
        physical_goods: {
            data: {}
        },
        rentals: {
            data: {}
        },
        services: {
            data: {}
        },
    }
    updateRoute: routeType
}>(), {
    reset : true,
    tabs: [
        {
            title: 'Rentals',
            value: 'rentals',
            key: 'rentals',
            tableBluprint: RentalBluprint
        },
        {
            title: 'Services',
            value: 'services',
            key: 'services',
            tableBluprint: ServicesBluprint
        },
        {
            title: 'Physical Goods',
            value: 'physical_goods',
            key: 'physical_goods',
            tableBluprint: PhysicalGoodsBluprint
        }
    ]
})

const cloneData = ref(cloneDeep(props.form))




const resetValue = () => {
    const clonedData = {
        rentals: cloneDeep(props.fieldData?.rentals?.data),
        physical_goods: cloneDeep(props.fieldData?.physical_goods?.data),
        services: cloneDeep(props.fieldData?.services.data),
    };
    props.form.defaults({
        [props.fieldName]: clonedData,
    })
    props.form.reset(props.fieldName)
}





onBeforeMount(() => {
    const clonedData = {
        rentals: cloneDeep(props.fieldData?.rentals?.data),
        physical_goods: cloneDeep(props.fieldData?.physical_goods?.data),
        services: cloneDeep(props.fieldData?.services?.data),
    };

    if (props.fieldData.value) {
        for (const l in clonedData) {
            if (l === 'rentals' && props.fieldData?.value?.rental) {
                clonedData[l] = clonedData[l].map((item) => {
                    const found = props.fieldData?.value?.rental.find((e) => e.asset_id === item.asset_id);
                    return found ? { ...item, ...found, agreed_price : parseFloat(found.agreed_price.toFixed(2)) } : item;
                });
            } else if (l === 'services' && props.fieldData?.value?.service) {
                clonedData[l] = clonedData[l].map((item) => {
                    const found = props.fieldData?.value?.service.find((e) => e.asset_id === item.asset_id);
                    return found ? { ...item, ...found, agreed_price : parseFloat(found.agreed_price.toFixed(2)) } : item;
                });
            } else if (l === 'physical_goods' && props.fieldData?.value?.product) {
                    clonedData[l] = clonedData[l].map((item) => {
                    const found = props.fieldData?.value?.product.find((e) => e.asset_id === item.asset_id);
                    return found ? { ...item, ...found, agreed_price : parseFloat(found.agreed_price.toFixed(2)) } : item;
                });
            }
        }
    }

    props.form.defaults({
        [props.fieldName]: clonedData,
    });
    props.form.reset(props.fieldName);
    cloneData.value = cloneDeep(props.form)
});

// Section: Replace the button from fieldform
const onSavedAgreement = (updateAll: boolean) => {
    props.form.transform((data) => ({
        ...data,
       /*  update_all: updateAll, */
    }))
    .post(route(props.updateRoute.name, props.updateRoute.parameters), { preserveScroll: true })
}

</script>


<template>
    <div class="border-2 rounded-lg p-3">
        <TabGroup>
            <TabList class="flex justify-between items-center pb-1 border-b-2">
                <div class="space-x-8">
                    <Tab v-for="tab in tabs" as="template" :key="tab.key" v-slot="{ selected }">
                        <button
                            :style="selected ? { color: layout.app.theme[0], borderBottomColor: layout.app.theme[0] } : {}"
                            :class="[
                                'whitespace-nowrap border-b-2 py-1.5 px-1 text-sm font-medium focus:ring-0 focus:outline-hidden',
                                selected
                                    ? `text-[${layout.app.theme[0]}]`
                                    : `border-transparent text-[${layout.app.theme[0]}] hover:border-[${layout.app.theme[0]}]`,
                            ]">
                            {{ tab.title }}
                            <span v-if="reset">
                                ({{
                                    props.form[props.fieldName][tab.tableBluprint.key].filter(xxx => xxx.price != xxx.agreed_price).length
                                    ? '+' + props.form[props.fieldName][tab.tableBluprint.key].filter(xxx => xxx.price != xxx.agreed_price).length
                                    : 0
                                }})
                            </span>
                        </button>
                    </Tab>
                </div>

                <div class="self-end flex gap-x-3 items-center">
                    <Button v-if="reset" label="Reset" size="s" :icon="['fal', 'history']" type="tertiary" @click="resetValue" />

                    <div class="">
                        <LoadingIcon v-if="form.processing" class="text-[23px]" />
                        <!-- <Popover v-else-if="form.isDirty && updateRoute?.name">
                            <template #button="{ open }"> -->
                                <FontAwesomeIcon @click="() => onSavedAgreement(false)" v-else-if="form.isDirty && updateRoute?.name" icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                            <!-- </template>
                            
                            <template #content="{ close: closed }">
                                <div class="max-w-min">
                                    <div class="mb-4 w-fit text-xs text-gray-500">{{trans('You can just update the discounts for future bills or apply it to current ones')}}</div>
                                    <div class="flex gap-x-2">
                                        <Button @click="() => (onSavedAgreement(false))" :label="trans('Update')" type="tertiary" />
                                        <Button @click="() => onSavedAgreement(true)" :label="trans('Update & Change Open Bills')" />
                                    </div>

                                    <div v-if="form.processing" class="absolute inset-0 bg-black/20 rounded-md flex justify-center items-center">
                                        <LoadingIcon class="text-white text-4xl" />
                                    </div>
                                </div>
                            </template>
                        </Popover> -->
                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                    </div>
                </div>
                

            </TabList>

            <TabPanels class="mt-2">
                <TabPanel v-for="(tab, idx) in tabs" :key="idx" class="p-3 focus:outline-hidden">
                    <slot name="table" :data="{ p: props, tab: tab }">
                        <RentalTable v-bind="props" :blueprint="tab.tableBluprint" :initalForm="cloneData" />
                    </slot>

                </TabPanel>
            </TabPanels>
        </TabGroup>

    </div>
</template>