<script setup lang="ts">
import { ref, computed, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import Dialog from "primevue/dialog"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"

const visible = defineModel<boolean>("visible", { required: true })

const props = defineProps<{
    shopId: number | null
}>()

const customerId = ref<number | null>(null)
const selectedCustomer = ref<any | null>(null)
const message = ref("")

const fetchRoute = computed(() => ({
    name: "grp.json.shop.customers",
    parameters: {
        shop: props.shopId,
        "filter[has_phone]": 1,
    },
}))

const onSelectCustomer = (customer: any) => {
    selectedCustomer.value = customer ?? null
}

watch(visible, (isVisible) => {
    if (!isVisible) {
        customerId.value = null
        selectedCustomer.value = null
        message.value = ""
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

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ trans("Message") }}</label>
                <textarea v-model="message" rows="4"
                    class="w-full text-sm border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1" />
            </div>

            <p class="text-xs text-gray-400 leading-snug">
                {{ trans("WhatsApp requires an approved template for the first message to a new contact.") }}
            </p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" class="px-3 py-1.5 text-sm text-gray-600 rounded-lg hover:bg-gray-100"
                    @click="visible = false">
                    {{ trans("Cancel") }}
                </button>
                <!-- ponytail: inert until a Meta send action + a seeded 'whatsapp' meta_channel exist. -->
                <button type="button" disabled v-tooltip="trans('Coming soon')"
                    class="px-3 py-1.5 text-sm text-white rounded-lg bg-gray-300 cursor-not-allowed">
                    {{ trans("Start chat") }}
                </button>
            </div>
        </div>
    </Dialog>
</template>
