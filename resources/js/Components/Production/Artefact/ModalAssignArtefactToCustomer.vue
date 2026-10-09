<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import AutoComplete from "primevue/autocomplete"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserTag, faArrowRight } from "@fal"

library.add(faUserTag, faArrowRight)

type ShopOption = { id: number; slug: string; code: string; name: string }
type CustomerOption = { id: number; slug: string; reference: string; name: string; email: string | null }

const props = defineProps<{
    artefactId: number
    shops: ShopOption[]
}>()

const isOpen = ref(false)
const shopId = ref<number | undefined>(props.shops[0]?.id)
const customer = ref<CustomerOption | null>(null)
const suggestions = ref<CustomerOption[]>([])

const searchCustomers = async (event: { query: string }) => {
    const searchedShopId = shopId.value
    const response = await axios.get(route("grp.json.shop.customers", { shop: searchedShopId }), {
        params: { "filter[global]": event.query, perPage: 20 },
    })
    if (searchedShopId === shopId.value) {
        suggestions.value = response.data.data
    }
}

const onShopChange = () => {
    customer.value = null
    suggestions.value = []
}

const goToCustomer = () => {
    const shop = props.shops.find((option) => option.id === shopId.value)
    if (!shop || !customer.value) {
        return
    }
    router.visit(route("grp.org.shops.show.crm.customers.show", {
        organisation: route().params.organisation,
        shop: shop.slug,
        customer: customer.value.slug,
        custom_product_artefact: props.artefactId,
    }))
}
</script>

<template>
    <div>
        <Button @click="isOpen = true" :label="ctrans('Assign to customer')" style="secondary" icon="fal fa-user-tag" />

        <Modal :isOpen="isOpen" @onClose="isOpen = false" width="w-full max-w-lg">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ ctrans("Make this artefact for a customer") }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ ctrans("Choose the customer. Their page opens with the custom product form ready, to set the code, price and units.") }}
                </p>

                <div class="mt-4 space-y-3">
                    <div v-if="shops.length > 1">
                        <label class="text-xs font-medium text-gray-600">{{ ctrans("Shop") }}</label>
                        <SegmentedToggle v-model="shopId"
                            :options="shops.map((shop) => ({ label: shop.code, value: shop.id }))"
                            @update:modelValue="onShopChange" />
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600">{{ ctrans("Customer") }}</label>
                        <AutoComplete v-model="customer" :suggestions="suggestions" optionLabel="name" forceSelection
                            :delay="300" :placeholder="ctrans('Search by name, reference or email')"
                            class="w-full" inputClass="w-full" @complete="searchCustomers">
                            <template #option="{ option }">
                                <span class="font-medium">{{ option.name }}</span>
                                <span class="ml-2 text-gray-500">#{{ option.reference }}</span>
                                <span v-if="option.email" class="ml-2 text-xs text-gray-400">{{ option.email }}</span>
                            </template>
                        </AutoComplete>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <Button :label="ctrans('Continue')" style="create" icon="fal fa-arrow-right"
                        :disabled="!customer?.slug" @click="goToCustomer" />
                </div>
            </div>
        </Modal>
    </div>
</template>
