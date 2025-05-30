<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { get, set } from 'lodash'
import { Column, DataTable, IconField, InputIcon, InputText } from 'primevue'
import { inject, onMounted, ref } from 'vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faSearch)

const model = defineModel<{}[]>()
const props = defineProps<{
    portfolios: {}[]
    listState: { [key: string]: { [key: string]: string } }
    progressToUploadToShopify: {
        [key: string]: string|null
    }
    platid: string
}>()

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
        const xxx = window.Echo.private(`shopify.${props.platid}.upload-product.${porto.id}`).listen(
            ".shopify-upload-progress",
            (eventData) => {
                // console.log('poppppppp', porto.id, eventData)
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

        // console.log('xxx', xxx)
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

        <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

        <Column field="code" header="Code" style="max-width: 90px;">
            <template #body="{ data }">
                <div v-tooltip="data.code" class="whitespace-nowrap truncate">
                    {{ data.code }}

                </div>
            </template>
        </Column>

        <Column field="category" header="Category" style="max-width: 200px;">

        </Column>

        <Column field="name" header="Name">
        </Column>

        <Column field="price" header="Price" style="max-width: 125px;">
            <template #body="{ data }">
                <div class="whitespace-nowrap">
                    {{ locale.currencyFormat(data.currency_code, data.price) }}
                </div>
            </template>
        </Column>

        <Column field="description" header="Description"></Column>

        <Column field="description" header="Action" style="text-align: right;">
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

                        <ButtonWithLink
                            :routeTarget="data.shopify_upload_portfolio"
                            label="Upload"
                            icon="fal fa-upload"
                            type="positive"
                            size="xs"
                            @success="() => set(props.progressToUploadToShopify, [data.id], 'loading')"
                        />
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
