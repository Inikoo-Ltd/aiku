<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { get, set } from 'lodash-es'
import { Column, DataTable, IconField, InputIcon, InputText } from 'primevue'
import { inject, onMounted, ref } from 'vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {faSearch, faRepeatAlt, faClone, faLink} from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faSearch, faRepeatAlt, faClone, faLink)

const model = defineModel<{}[]>()
const props = defineProps<{
    portfolios: {}[]
    listState: { [key: string]: { [key: string]: string } }
    progressToUploadToShopify: {
        [key: string]: string|null
    }
    platform_data: {
        id: number
        code: string
        type: string
        name: string
    }
    platid: number
}>()

const selectSocketi = (porto: {}) => {
    if (props.platform_data.type === 'shopify') {
        return {
            event: `shopify.${props.platid}.upload-product.${porto.id}`,
            action: '.shopify-upload-progress'
        }
    } else if (props.platform_data.type === 'woocommerce') {
        return {
            event: `woo.${props.platid}.upload-product.${porto.id}`,
            action: '.woo-upload-progress'
        }
    } else if (props.platform_data.type === 'ebay') {
        return {
            event: `ebay.${props.platid}.upload-product.${porto.id}`,
            action: '.ebay-upload-progress'
        }
    } else if (props.platform_data.type === 'amazon') {
        return {
            event: `amazon.${props.platid}.upload-product.${porto.id}`,
            action: '.amazon-upload-progress'
        }
    } else if (props.platform_data.type === 'magento') {
        return {
            event: `magento.${props.platid}.upload-product.${porto.id}`,
            action: '.magento-upload-progress'
        }
    }
}

const emits = defineEmits<{
    (e: "updateSelectedProducts", portfolio: {}, dataToSend: {}, keyToConditionicon: string ): void,
    (e: "mounted"): void,
    (e: "portfolioDeleted", value: {}): void,
}>()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', layoutStructure)
// console.log('layout', layout.user?.id)

const disabledRowId = ref([])
onMounted(() => {
    emits('mounted')
    props.portfolios.forEach(porto => {
        console.log('porto', selectSocketi(porto))
        const xxx = window.Echo.private(selectSocketi(porto)?.event).listen(
            selectSocketi(porto)?.action,
            (eventData) => {
                console.log('poppppppp', porto.id, eventData)
                if(eventData.errors_response) {
                    set(props.progressToUploadToShopify, [porto.id], 'error')
                    setTimeout(() => {
                        set(props.progressToUploadToShopify, [porto.id], null)
                    }, 3000);

                } else {
                    set(props.progressToUploadToShopify, [porto.id], 'success')
                    disabledRowId.value.push(porto.id)
                }
            }
        );

    });

})


// PrimeVue trick
// const isSelectedAll = ref(false);
// const rowUnselectHook = () => {
//     isSelectedAll.value = false;
// };

// const selectAllChangeHook = (event) => {
//     isSelectedAll.value = event.checked;
//     if (event.checked) {
//         console.log('mmomo', props.portfolios, disabledRowId.value)
//         model.value = props.portfolios.filter((row) => !disabledRowId.value.includes(row.id));
//     } else {
//         model.value = [];
//     }
// };

const valueTableFilter = ref({})
</script>

<template>
    <DataTable
        v-model:selection="model"
        :value="portfolios"
        tableStyle="min-width: 50rem"
        :rowClass="(data) => disabledRowId.includes(data.id) ? 'p-disabled-checkbox' : ''"
        xselect-all="isSelectedAll"
        aselect-all-change="selectAllChangeHook"
        arow-unselect="rowUnselectHook"
    >
        <template #header>
            <div class="flex justify-between items-center">
                <div class="text-xl">
                    Total: <span class="font-bold">{{ portfolios.length }}</span>
                </div>

                <IconField>
                    <InputIcon>
                        <FontAwesomeIcon icon="fal fa-search" class="" fixed-width aria-hidden="true" />
                    </InputIcon>
                    <InputText
                        :modelValue="get(valueTableFilter, 'global.value', '')"
                        @update:model-value="(e) => (console.log(e), set(valueTableFilter, ['global', 'value'], e))"
                        :placeholder="trans('Search in table')"
                    />
                </IconField>
            </div>
        </template>

        <!-- <Column selectionMode="multiple" headerStyle="width: 3rem"></Column> -->

        <Column field="code"  style="max-width: 125px;">
             <template #header>
                <div v-tooltip="'Code'" class="whitespace-nowrap truncate font-semibold">
                Code
                </div>
            </template>
            <template #body="{ data }">
                <div v-tooltip="data.code" class="whitespace-nowrap truncate">
                    {{ data.code }}
                </div>
            </template>
        </Column>

<!--        <Column field="category" header="Category" style="max-width: 200px;">-->

<!--        </Column>-->

        <Column field="name" >
            <template #header>
                <div v-tooltip="'Name'" class="whitespace-nowrap truncate font-semibold">
                    Name
                </div>
            </template>

            <template #body="{ data }">
                <div>
                    <div class="whitespace-nowrap xtext-right w-full">
                        {{ data.name }}
                    </div>

                    <div v-if="data.is_code_exist_in_platform" class="text-xs text-amber-500">
                        <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="" fixed-width aria-hidden="true" />
                        <span class="pr-2">We found same product in your shop, do you want to create new or use existing? (default: use existing):</span>
                        <Button label="Use Existing" icon="fal fa-link" :disabled="data?.product_availability?.options === 'use_existing'" :type="data?.product_availability?.options === 'use_existing' ? 'primary' : 'tertiary'" size="xxs" />
                        <span class="px-2 text-gray-500">or</span>
                        <Button label="Create new" icon="fal fa-clone" type="tertiary" size="xxs" />
                    </div>
                </div>
            </template>
        </Column>

        <Column field="price"  style="max-width: 125px; text-align: right;">
              <template #header>
                <div v-tooltip="'Price'" class="whitespace-nowrap truncate font-semibold text-right w-full">
                Price
                </div>
            </template>

            <template #body="{ data }">
                <div  class="whitespace-nowrap text-right w-full">
                    {{ locale.currencyFormat(data.currency_code, data.price) }}
                </div>
            </template>
        </Column>

<!--        <Column field="description" header="Description">-->
<!--            <template #body="{ data }">-->
<!--                <div v-if="data.description" v-html="data.description" class="h-fit max-h-32 overflow-y-auto shadow border border-gray-300 px-1 rounded">-->

<!--                </div>-->
<!--                <div v-else class="text-gray-400 italic text-sm">-->
<!--                    (No description)-->
<!--                </div>-->
<!--            </template>-->
<!--        </Column>-->

            <Column field="customer_price" style="max-width: 125px; text-align: right;">
                <template #header>
                    <div
                    v-tooltip="'Recommended retail price'"
                    class="whitespace-nowrap truncate font-semibold text-right w-full"
                    >
                    RRP
                    </div>
                </template>

                <template #body="{ data }">
                    <div class="whitespace-nowrap text-right w-full">
                    {{ locale.currencyFormat(data.currency_code, data.customer_price) }}
                    </div>
                </template>
            </Column>



        <Column field="action" style="text-align: right;">
            <template #body="{ data }">
                <div class="flex gap-x-2 gap-y-1 flex-wrap justify-end">
                    <ConditionIcon v-if="get(props.progressToUploadToShopify, [data.id], null)" :state="get(props.progressToUploadToShopify, [data.id], undefined)" class="text-xl mx-auto" />

                    <template v-else>

                        <ButtonWithLink
                            :routeTarget="data.delete_portfolio"
                            label="Remove"
                            type="delete"
                            size="xs"
                            @success="() => emits('portfolioDeleted', data)"
                        />

                        <!-- <ButtonWithLink
                            :routeTarget="data.platform_upload_portfolio"
                            label="Upload"
                            icon="fal fa-upload"
                            type="positive"
                            size="xs"
                            @success="() => set(props.progressToUploadToShopify, [data.id], 'loading')"
                        /> -->
                    </template>
                </div>
            </template>
        </Column>
    </DataTable>
</template>

<style scoped>
:deep(.p-disabled-checkbox .p-checkbox) {
    cursor: default !important;
    pointer-events: none;
    user-select: none;
}
</style>
