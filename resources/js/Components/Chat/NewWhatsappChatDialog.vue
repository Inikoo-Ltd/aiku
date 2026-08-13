<script setup lang="ts">
import { ref, computed, inject, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import Dialog from "primevue/dialog"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"

const visible = defineModel<boolean>("visible", { required: true })

const props = defineProps<{
    shopId: number | null
}>()

const emits = defineEmits<{
    (e: "created", session: any): void
}>()

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const customerId = ref<number | null>(null)
const selectedCustomer = ref<any | null>(null)
const submitting = ref(false)
const error = ref("")

const fetchRoute = computed(() => ({
    name: "grp.json.shop.customers",
    parameters: {
        shop: props.shopId,
        "filter[has_phone]": 1,
    },
}))

const onSelectCustomer = (customer: any) => {
    selectedCustomer.value = customer ?? null
    error.value = ""
}

const startChat = async () => {
    if (!props.shopId || !customerId.value || submitting.value) return
    submitting.value = true
    error.value = ""
    try {
        const res = await axios.post(`${baseUrl}/app/api/chats/meta/sessions`, {
            shop_id: props.shopId,
            customer_id: customerId.value,
        })
        emits("created", res.data.data ?? res.data)
        visible.value = false
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? trans("Failed to start the chat.")
    } finally {
        submitting.value = false
    }
}

watch(visible, (isVisible) => {
    if (!isVisible) {
        customerId.value = null
        selectedCustomer.value = null
        error.value = ""
    }
})
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="trans('New WhatsApp chat')"
        :style="{ width: '90vw', maxWidth: '440px' }" :breakpoints="{ '640px': '95vw' }">
        <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ trans("Customer") }}</label>
                <PureMultiselectInfiniteScroll :key="shopId" v-model="customerId" :fetchRoute="fetchRoute"
                    valueProp="id" labelProp="name" labelAdditionalProp="reference"
                    :placeholder="trans('Search customer')"
                    :noOptionsText="trans('No customers with a phone number')"
                    @selectedObject="onSelectCustomer">
                    <template #singlelabel="{ value }">
                        <div class="w-full text-left pl-4 leading-4 truncate mr-2">
                            {{ value.name }}
                            <span class="text-sm text-gray-400">
                                ({{ value.reference }}<template v-if="value.phone"> · {{ value.phone }}</template>)
                            </span>
                        </div>
                    </template>

                    <template #option="{ option }">
                        <div>
                            {{ option.name }}
                            <span class="text-sm text-gray-400">
                                ({{ option.reference }}<template v-if="option.phone"> · {{ option.phone }}</template>)
                            </span>
                        </div>
                    </template>
                </PureMultiselectInfiniteScroll>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ trans("Phone number") }}</label>
                <div class="w-full text-sm border rounded-lg px-3 py-1.5 bg-gray-50"
                    :class="selectedCustomer?.phone ? 'text-gray-700' : 'text-gray-400'">
                    {{ selectedCustomer?.phone || trans("Select a customer") }}
                </div>
            </div>

            <p class="text-xs text-gray-400 leading-snug">
                {{ trans("WhatsApp requires an approved template for the first message to a new contact.") }}
            </p>

            <p v-if="error" class="text-xs text-red-500 leading-snug">{{ error }}</p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" class="px-3 py-1.5 text-sm text-gray-600 rounded-lg hover:bg-gray-100"
                    @click="visible = false">
                    {{ trans("Cancel") }}
                </button>
                <button type="button" :disabled="!selectedCustomer?.phone || submitting" @click="startChat"
                    class="px-3 py-1.5 text-sm text-white rounded-lg"
                    :class="!selectedCustomer?.phone || submitting
                        ? 'bg-gray-300 cursor-not-allowed'
                        : 'bg-green-600 hover:bg-green-700'">
                    {{ submitting ? trans("Starting...") : trans("Start chat") }}
                </button>
            </div>
        </div>
    </Dialog>
</template>
