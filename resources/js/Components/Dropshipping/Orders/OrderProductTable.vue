<script setup lang='ts'>
import Button from '@/Components/Elements/Buttons/Button.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import Table from '@/Components/Table/Table.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { routeType } from '@/types/route'
import { Table as TableTS} from '@/types/Table'
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash'
import { inject, ref } from 'vue'

const props = defineProps<{
    data: any[] | TableTS
    tab: string
    updateRoute: routeType
    state?: string
    readonly?: boolean
}>()
    
function productRoute(product) {
    // console.log(route().current())
    switch (route().current()) {
        case 'grp.org.shops.show.crm.customers.show.orders.show':
        case 'grp.org.shops.show.ordering.orders.show':
            if(product.product_slug) {
                return route(
                    'grp.org.shops.show.catalogue.products.all_products.show',
                    [route().params['organisation'], route().params['shop'], product.product_slug])
            }
            return ''
        default:
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
            onStart: () => isLoading.value = 'quantity' + idTransaction,
            onFinish: () => isLoading.value = false,
            only: ['transactions', 'box_stats'],
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
        <!-- <template #cell(net_amount)="{ item }">
            {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
        </template> -->

        <!-- Column: Quantity -->
        <template #cell(quantity_ordered)="{ item }">
            <div class="flex items-center justify-end">
                <Transition name="spin-to-down">
                    <span :key="item.quantity_ordered">
                        {{ item['quantity_ordered'] }}
                    </span>
                </Transition>

                <!-- <PureInput
                    :modelValue="item.quantity_ordered"
                    @onEnter="(e: number) => onUpdateQuantity(item.updateRoute, item.id, e)"
                    @blur="(e: string) => e == item.quantity_ordered ? false : onUpdateQuantity(item.updateRoute, item.id, e)"
                    :isLoading="isLoading === 'quantity' + item.id"
                    type="number"
                    align="right"
                    :disabled="state === 'dispatched'"
                /> -->
            </div>

            <!-- <div v-else>{{ item.quantity }}</div> -->
        </template>

        <!-- Column: Action -->
        <template #cell(actions)="{ item }">
            <div class="flex gap-2">
                <div v-if="state === 'creating' || state === 'submitted'" class="w-fit">
                    <NumberWithButtonSave
                        :modelValue="item.quantity_ordered"
                        :routeSubmit="item.updateRoute"
                        :bindToTarget="{ min: 0 }"
                        keySubmit="quantity_ordered"
                        :isLoading="isLoading === 'quantity' + item.id"
                        :readonly="readonly"
                        @update:modelValue="(e: number) => debounceUpdateQuantity(item.updateRoute, item.id, e)"
                        noUndoButton
                        noSaveButton
                    />
                </div>
                
                <Link
                    v-if="state === 'creating' || state === 'submitted'"
                    :href="route(item.deleteRoute.name, item.deleteRoute.parameters)"
                    as="button"
                    :method="item.deleteRoute.method"
                    @start="() => isLoading = 'unselect' + item.id"
                    @finish="() => isLoading = false"
                    v-tooltip="trans('Unselect this product')"
                >
                    <Button v-if="!readonly" icon="fal fa-times" type="negative" size="xs" :loading="isLoading === 'unselect' + item.id" />
                </Link>
            </div>
            <!-- {{ locale.currencyFormat(item.currency_code, item.net_amount) }} -->
        </template>
    </Table>
</template>