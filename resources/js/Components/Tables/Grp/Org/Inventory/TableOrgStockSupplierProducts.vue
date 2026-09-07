<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Wed, 15 Jul 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { ref } from "vue"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrophy as falTrophy, faSnooze, faPallet, faStopCircle, faTimes } from "@fal"
import { faTrophy as fasTrophy } from "@fas"

library.add(falTrophy, faSnooze, fasTrophy, faPallet, faStopCircle, faTimes)

const props = defineProps<{
    data: object
    tab?: string
    org_stock_id?: number
}>()

const locale = useLocaleStore()
const routeParams = route().params as { organisation: string }
const orgSupplierProductToAttach = ref<number | null>(null)
const isAttaching = ref(false)

function attachSupplierProduct() {
    if (!props.org_stock_id || !orgSupplierProductToAttach.value) return
    isAttaching.value = true
    router.post(
        route("grp.models.org_stock.supplier_product.attach", [props.org_stock_id, orgSupplierProductToAttach.value]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => (orgSupplierProductToAttach.value = null),
            onFinish: () => (isAttaching.value = false),
        }
    )
}

function setPreferred(supplierProduct: any) {
    router.patch(
        route("grp.models.org_stock.supplier_product.set_preferred", [
            supplierProduct.org_stock_id,
            supplierProduct.org_supplier_product_id,
        ]),
        {},
        { preserveScroll: true }
    )
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #add-on-button>
            <div v-if="org_stock_id" class="flex items-center gap-2">
                <div class="w-72">
                    <PureMultiselectInfiniteScroll
                        mode="single"
                        v-model="orgSupplierProductToAttach"
                        :fetchRoute="{ name: 'grp.json.org_supplier_products.index', parameters: { organisation: routeParams.organisation } }"
                        valueProp="id"
                        labelProp="code"
                        labelAdditionalProp="name"
                        :placeholder="trans('Search supplier product')" />
                </div>
                <Button
                    :label="trans('Attach supplier')"
                    icon="fal fa-link"
                    size="s"
                    :loading="isAttaching"
                    :disabled="!orgSupplierProductToAttach"
                    @click="attachSupplierProduct" />
            </div>
        </template>

        <template #cell(preferred)="{ item: supplierProduct }">
            <Icon
                v-if="supplierProduct.is_preferred"
                :data="{ icon: 'fas fa-trophy', class: 'text-amber-500', tooltip: trans('Preferred supplier') }" />
            <Icon
                v-else
                :data="{ icon: 'fal fa-snooze', class: 'text-gray-400', tooltip: trans('Backup supplier') }" />
        </template>

        <template #cell(unit_cost)="{ item: supplierProduct }">
            {{ locale.currencyFormat(supplierProduct.currency_code, supplierProduct.unit_cost) }}
        </template>

        <template #cell(delivered_unit_cost)="{ item: supplierProduct }">
            <span v-if="supplierProduct.delivered_unit_cost !== null">
                {{ locale.currencyFormat(supplierProduct.org_currency_code, supplierProduct.delivered_unit_cost) }}
            </span>
            <span v-else class="text-gray-300">—</span>
        </template>

        <template #cell(units_per_carton)="{ item: supplierProduct }">
            <span v-tooltip="trans('Units per carton')" class="inline-flex items-center gap-0.5">
                <Icon :data="{ icon: 'fal fa-stop-circle', class: 'text-gray-300' }" />
                <Icon :data="{ icon: 'fal fa-times', class: 'text-gray-300' }" />
                <span>{{ supplierProduct.units_per_carton }}</span>
            </span>
            <span
                v-if="supplierProduct.packages_per_carton"
                v-tooltip="trans('Packages (SKOs) per carton')"
                class="text-gray-400">
                ({{ supplierProduct.packages_per_carton }})
            </span>
        </template>

        <template #cell(set_preferred)="{ item: supplierProduct }">
            <button
                v-if="!supplierProduct.is_preferred"
                type="button"
                class="inline-flex items-center gap-1 text-gray-400 hover:text-amber-500"
                v-tooltip="trans('Set as preferred supplier')"
                @click="setPreferred(supplierProduct)">
                <span class="text-xs">{{ trans("Set as") }}</span>
                <Icon :data="{ icon: 'fal fa-trophy' }" />
            </button>
        </template>
    </Table>
</template>
