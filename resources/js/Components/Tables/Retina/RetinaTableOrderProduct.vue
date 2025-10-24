<script setup lang='ts'>
import Button from '@/Components/Elements/Buttons/Button.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import Table from '@/Components/Table/Table.vue'
import Tag from '@/Components/Tag.vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { routeType } from '@/types/route'
import { Table as TableTS } from '@/types/Table'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import { inject, ref } from 'vue'

defineProps<{
    data: any[] | TableTS
    tab: string
    updateRoute: routeType
    state?: string
    readonly?: boolean
}>()


const locale = inject('locale', retinaLayoutStructure)


function productRoute(product) {
    if (product.product_slug) {
        return route(
            'retina.catalogue.products.show',
            [product.product_slug])
    }else{
        return ''
    }
}



// Section: Quantity
const isLoading = ref<string | boolean>(false)
const onUpdateQuantity = (routeUpdate: routeType, idTransaction: number, value: number) => {
    router.patch(
        route(routeUpdate.name, routeUpdate.parameters),
        {
            quantity_ordered: Number(value)
        },
        {
            onError: (e: any) => {
                notify({
                    title: trans("Something went wrong"),
                    text: e.message,
                    type: "error",
                })
            },
            onStart: () => isLoading.value = 'quantity' + idTransaction,
            onFinish: () => isLoading.value = false,
            only: ['transactions', 'box_stats', 'total_to_pay', 'balance'],
            preserveScroll: true,
        }
    )
}


const debounceUpdateQuantity = debounce(
    (routeUpdate: routeType, idTransaction: number, value: number) => {
        onUpdateQuantity(routeUpdate, idTransaction, value)
    },
    500
)

</script>

<template>
    <Table :resource="data" :name="tab">
        <!-- Column: Code -->
        <template #cell(asset_code)="{ item }">
            <Link :href="productRoute(item)" class="primaryLink">
            {{ item.asset_code }}
            </Link>
        </template>

        <!-- Column: Net -->
        <template #cell(asset_name)="{ item }">
            <div>
                <div>{{ item.asset_name }}</div>
                <div v-if="typeof item.available_quantity !== 'undefined' && item.available_quantity < 1">
                    <Tag label="Out of stock" no-hover-color :theme="7" size="xxs" />
                </div>
                <div v-else class="text-gray-500 italic text-xs">
                    Stock: {{ locale.number(item.available_quantity || 0) }} available
                </div>

            </div>
        </template>

        <!-- Column: Quantity -->
        <template #cell(quantity_ordered)="{ item }">
            <div class="flex items-center justify-end">
                <div v-if="state === 'creating' || state === 'xsubmitted'" class="w-fit">
                    <NumberWithButtonSave :modelValue="item.quantity_ordered" :routeSubmit="item.updateRoute"
                        :bindToTarget="{ min: 0 }" isWithRefreshModel keySubmit="quantity_ordered"
                        :isLoading="isLoading === 'quantity' + item.id" :readonly="readonly"
                        @update:modelValue="(e: number) => debounceUpdateQuantity(item.updateRoute, item.id, e)"
                        noUndoButton noSaveButton />
                </div>

                <div v-else>
                    {{
                        Number.isInteger(Number(item.quantity_ordered)) &&
                            String(item.quantity_ordered).match(/^\d+(\.0+)?$/)
                            ? parseInt(item.quantity_ordered)
                            : parseFloat(item.quantity_ordered)
                    }}
                </div>


            </div>

        </template>

        <!-- Column: Action -->
        <template #cell(actions)="{ item }">
            <div class="flex gap-2">
                <Link v-if="state === 'creating' || state === 'xsubmitted'"
                    :href="route(item.deleteRoute.name, item.deleteRoute.parameters)" as="button"
                    :method="item.deleteRoute.method" @start="() => isLoading = 'unselect' + item.id"
                    @finish="() => isLoading = false" v-tooltip="trans('Unselect this product')" :preserveScroll="true">
                <Button v-if="!readonly" icon="fal fa-times" type="negative" size="xs"
                    :loading="isLoading === 'unselect' + item.id" />
                </Link>
            </div>
        </template>
    </Table>
</template>