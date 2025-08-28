<script setup lang='ts'>
import Button from '@/Components/Elements/Buttons/Button.vue'
import Image from '@/Components/Image.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import Table from '@/Components/Table/Table.vue'
import Tag from '@/Components/Tag.vue'
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { routeType } from '@/types/route'
import { Table as TableTS} from '@/types/Table'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { debounce, get, set } from 'lodash-es'
import { inject, ref } from 'vue'

const props = defineProps<{
    data: any[] | TableTS
    tab?: string
    updateRoute: routeType
    state?: string
    readonly?: boolean
}>()
    

const locale = inject('locale', retinaLayoutStructure)

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
const listState = ref({})
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
            onStart: () => {
                set(listState.value, [idTransaction, 'quantity'], 'loading'),
                isLoading.value = 'quantity' + idTransaction
            },
            onSuccess: () => {
                set(listState.value, [idTransaction, 'quantity'], 'success')
            },
            onFinish: () => {
                isLoading.value = false,
                setTimeout(() => {
                    set(listState.value, [idTransaction, 'quantity'], null)
                }, 3000)
            },
            only: ['transactions', 'summary', 'total_to_pay', 'balance'],
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
        <!-- Column: Image -->
        <template #cell(image)="{ item }">
            <div class="flex relative w-8 aspect-square overflow-hidden">
                <Image
                    :src="item.image.thumbnail"
                    class="w-full h-full object-contain"
                />
            </div>
        </template>

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
                <div v-if="!item.available_quantity">
                    <Tag label="Out of stock" no-hover-color :theme="7" size="xxs" />
                </div>
                <div v-else class="text-gray-500 italic text-xs">
                    Stock: {{ locale.number(item.available_quantity || 0) }} available
                </div>
                
            </div>
        </template>

        <!-- Column: Quantity -->
        <template #cell(quantity_ordered)="{ item }">
            <div class="px-2 relative text-right w-full">
                <!-- <pre>{{ item }}</pre> -->
                <div class="w-fit ml-auto">
                    <NumberWithButtonSave
                        v-model="item.quantity_ordered"
                        @update:modelValue="(value: number) => {
                            debounceUpdateQuantity(item.updateRoute, item.id, value)
                        }"
                        :routeSubmit="item.updateRoute"
                        key-submit="quantity_ordered"
                        xxsaveOnForm
                        noSaveButton
                        noUndoButton
                        :min="1"
                    />
                </div>

                <ConditionIcon
                    class="absolute ml-2 top-1/2 -translate-y-1/2 text-base"
                    :state="get(listState, [item.id, 'quantity'], null)"
                />
                
            </div>
        </template>

        <!-- Column: Action -->
        <template #cell(actions)="{ item }">
            <div class="flex gap-2 px-2">
                <Link
                    :href="item.deleteRoute?.name ? route(item.deleteRoute.name, item.deleteRoute.parameters) : '#'"
                    as="button"
                    :method="item.deleteRoute.method"
                    @start="() => isLoading = 'unselect' + item.id"
                    @finish="() => isLoading = false"
                    v-tooltip="trans('Unselect this product')"
                    :preserveScroll="true"
                >
                    <Button icon="fal fa-times" type="negative" size="xs" :loading="isLoading === 'unselect' + item.id" />
                </Link>
            </div>
        </template>
    </Table>

    <!-- <div>
        <div>
            <div :colspan="4">
                <div>
                    <div class="px-2 flex justify-end relative">
                        For the same day dispatch of your order before 12pm (£7.50)
                    </div>
                </div>
            </div>
            <div>
                <div>
                    <div class="px-2 flex justify-end relative">
                        <Toggle :modelValue="true" />
                    </div>
                </div>
            </div>
        </div>
        
        <div>
            <div :colspan="4">
                <div>
                    <div class="px-2 flex justify-end relative">
                        Glass & ceramics insurance (£2.75)
                    </div>
                </div>
            </div>
            <div>
                <div>
                    <div class="px-2 flex justify-end relative">
                        <Toggle :modelValue="true" />
                    </div>
                </div>
            </div>
        </div>
    </div> -->
</template>