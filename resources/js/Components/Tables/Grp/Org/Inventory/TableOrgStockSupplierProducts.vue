<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Wed, 15 Jul 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link, router, useForm } from "@inertiajs/vue3"
import { ref } from "vue"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrophy as falTrophy, faSnooze, faPallet, faStopCircle, faTimes, faPlus } from "@fal"
import { faTrophy as fasTrophy } from "@fas"

library.add(falTrophy, faSnooze, fasTrophy, faPallet, faStopCircle, faTimes, faPlus)

const props = defineProps<{
    data: object
    tab?: string
    org_stock_id?: number
    can_link_supplier_products?: boolean
    can_create_supplier_products?: boolean
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

const isNewSupplierProductOpen = ref(false)
const newSupplierCurrency = ref<string | null>(null)
const newSupplierProduct = useForm({
    org_supplier_id: null as number | null,
    code: "",
    name: "",
    cost: "",
    units_per_pack: 1,
    units_per_carton: "",
})

function storeSupplierProduct() {
    if (!props.org_stock_id) return
    newSupplierProduct.post(route("grp.models.org_stock.supplier_product.store", [props.org_stock_id]), {
        preserveScroll: true,
        onSuccess: () => {
            newSupplierProduct.reset()
            isNewSupplierProductOpen.value = false
        },
    })
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
    <div>
        <Table :resource="data" :name="tab" class="mt-5">
            <template #add-on-button>
                <div v-if="org_stock_id && (can_link_supplier_products || can_create_supplier_products)" class="flex items-center gap-2">
                    <div v-if="can_link_supplier_products" class="w-72">
                        <PureMultiselectInfiniteScroll
                            mode="single"
                            v-model="orgSupplierProductToAttach"
                            :fetchRoute="{ name: 'grp.json.org_supplier_products.index', parameters: { organisation: routeParams.organisation } }"
                            valueProp="id"
                            labelProp="code"
                            labelAdditionalProp="name"
                            :placeholder="ctrans('Search supplier product')" />
                    </div>
                    <Button
                        v-if="can_link_supplier_products"
                        :label="ctrans('Attach supplier')"
                        icon="fal fa-link"
                        size="s"
                        :loading="isAttaching"
                        :disabled="!orgSupplierProductToAttach"
                        @click="attachSupplierProduct" />
                    <Button
                        v-if="can_create_supplier_products"
                        :label="ctrans('Add supplier')"
                        icon="fal fa-plus"
                        size="s"
                        type="secondary"
                        @click="isNewSupplierProductOpen = true" />
                </div>
            </template>

            <template #cell(supplier_name)="{ item: supplierProduct }">
                <Link
                    v-if="supplierProduct.org_supplier_slug"
                    :href="route('grp.org.procurement.org_suppliers.show', [routeParams.organisation, supplierProduct.org_supplier_slug])"
                    class="primaryLink">
                    {{ supplierProduct.supplier_name }}
                </Link>
                <span v-else>{{ supplierProduct.supplier_name }}</span>
            </template>

            <template #cell(preferred)="{ item: supplierProduct }">
                <Icon
                    v-if="supplierProduct.is_preferred"
                    :data="{ icon: 'fas fa-trophy', class: 'text-amber-500', tooltip: ctrans('Preferred supplier') }" />
                <Icon
                    v-else
                    :data="{ icon: 'fal fa-snooze', class: 'text-gray-400', tooltip: ctrans('Backup supplier') }" />
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
                <span v-tooltip="ctrans('Units per carton')" class="inline-flex items-center gap-0.5">
                    <Icon :data="{ icon: 'fal fa-stop-circle', class: 'text-gray-300' }" />
                    <Icon :data="{ icon: 'fal fa-times', class: 'text-gray-300' }" />
                    <span>{{ supplierProduct.units_per_carton }}</span>
                </span>
                <span
                    v-if="supplierProduct.packages_per_carton"
                    v-tooltip="ctrans('Packages (SKOs) per carton')"
                    class="text-gray-400">
                    ({{ supplierProduct.packages_per_carton }})
                </span>
            </template>

            <template #cell(set_preferred)="{ item: supplierProduct }">
                <button
                    v-if="can_link_supplier_products && !supplierProduct.is_preferred"
                    type="button"
                    class="inline-flex items-center gap-1 text-gray-400 hover:text-amber-500"
                    v-tooltip="ctrans('Set as preferred supplier')"
                    @click="setPreferred(supplierProduct)">
                    <span class="text-xs">{{ ctrans("Set as") }}</span>
                    <Icon :data="{ icon: 'fal fa-trophy' }" />
                </button>
            </template>
        </Table>

        <Modal :isOpen="isNewSupplierProductOpen" width="w-full max-w-lg" @onClose="isNewSupplierProductOpen = false">
            <form class="space-y-4" @submit.prevent="storeSupplierProduct">
                <h3 class="text-lg font-semibold">{{ ctrans("Add supplier to this SKO") }}</h3>

                <div>
                    <div class="mb-1 flex items-center justify-between text-sm font-medium">
                        <span>{{ ctrans("Supplier") }}</span>
                        <Link
                            :href="route('grp.org.procurement.org_suppliers.create_new', [routeParams.organisation])"
                            class="primaryLink text-xs font-normal">
                            {{ ctrans("New supplier") }}
                        </Link>
                    </div>
                    <PureMultiselectInfiniteScroll
                        mode="single"
                        v-model="newSupplierProduct.org_supplier_id"
                        :fetchRoute="{ name: 'grp.json.org_suppliers.index', parameters: { organisation: routeParams.organisation } }"
                        valueProp="id"
                        labelProp="code"
                        labelAdditionalProp="name"
                        :placeholder="ctrans('Search supplier')"
                        @selectedObject="(supplier: any) => (newSupplierCurrency = supplier?.currency_code ?? null)" />
                    <p v-if="newSupplierProduct.errors.org_supplier_id" class="mt-1 text-xs text-red-500">{{ newSupplierProduct.errors.org_supplier_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">{{ ctrans("Supplier's code") }}</label>
                    <PureInput v-model="newSupplierProduct.code" required />
                    <p v-if="newSupplierProduct.errors.code" class="mt-1 text-xs text-red-500">{{ newSupplierProduct.errors.code }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">{{ ctrans("Supplier's unit description") }}</label>
                    <PureInput v-model="newSupplierProduct.name" required />
                    <p v-if="newSupplierProduct.errors.name" class="mt-1 text-xs text-red-500">{{ newSupplierProduct.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        {{ ctrans("Unit cost") }}<span v-if="newSupplierCurrency"> ({{ newSupplierCurrency }})</span>
                    </label>
                    <PureInput v-model="newSupplierProduct.cost" type="number" required />
                    <p v-if="newSupplierProduct.errors.cost" class="mt-1 text-xs text-red-500">{{ newSupplierProduct.errors.cost }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ ctrans("Units per SKO") }}</label>
                        <PureInput v-model="newSupplierProduct.units_per_pack" type="number" required />
                        <p v-if="newSupplierProduct.errors.units_per_pack" class="mt-1 text-xs text-red-500">{{ newSupplierProduct.errors.units_per_pack }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ ctrans("Units per carton") }}</label>
                        <PureInput v-model="newSupplierProduct.units_per_carton" type="number" required />
                        <p v-if="newSupplierProduct.errors.units_per_carton" class="mt-1 text-xs text-red-500">{{ newSupplierProduct.errors.units_per_carton }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button :label="ctrans('Cancel')" type="tertiary" @click="isNewSupplierProductOpen = false" />
                    <Button :label="ctrans('Save')" type="save" :loading="newSupplierProduct.processing" :disabled="!newSupplierProduct.org_supplier_id" @click="storeSupplierProduct" />
                </div>
            </form>
        </Modal>
    </div>
</template>
