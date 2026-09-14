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

const searchTerm = ref("")
const contactName = ref("")
const noResults = ref(false)

const looksLikePhone = computed(() => /^[+\d][\d\s\-().]*$/.test(searchTerm.value.trim()))

const normalisedPhone = computed(() => {
    const value = searchTerm.value.trim()
    return value.startsWith("+") ? "+" + value.replace(/\D/g, "") : value
})

const phoneError = computed(() => {
    const value = searchTerm.value.trim()
    if (!value || !looksLikePhone.value) return ""
    if (!value.startsWith("+")) {
        return trans("Phone number must start with '+' and the country code")
    }
    if (!/^\+[1-9]\d{7,14}$/.test(normalisedPhone.value)) {
        return trans("Enter a valid phone number")
    }
    return ""
})

const isPhoneValid = computed(
    () => looksLikePhone.value && !!searchTerm.value.trim() && !phoneError.value
)

const showNewContact = computed(
    () => !selectedCustomer.value && noResults.value && isPhoneValid.value
)

const canStart = computed(() =>
    submitting.value ? false : !!selectedCustomer.value?.phone || isPhoneValid.value
)

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

    if (selectedCustomer.value) {
        noResults.value = false
        contactName.value = ""
    }
}

const onOptionsList = (options: any[]) => {
    noResults.value = !!searchTerm.value.trim() && options.length === 0
}

const onSearchChange = (query: string) => {
    searchTerm.value = query ?? ""
    if (!searchTerm.value.trim()) {
        noResults.value = false
        contactName.value = ""
    }
}

const startChat = async () => {
    if (!props.shopId || !canStart.value) return
    submitting.value = true
    error.value = ""
    try {
        const payload: Record<string, any> = { shop_id: props.shopId }

        if (selectedCustomer.value) {
            payload.customer_id = selectedCustomer.value.id
        } else {
            payload.phone_number = normalisedPhone.value
            payload.name = contactName.value.trim() || normalisedPhone.value
        }

        const res = await axios.post(`${baseUrl}/app/api/chats/meta/sessions`, payload, { withCredentials: true })
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
        searchTerm.value = ""
        contactName.value = ""
        noResults.value = false
        error.value = ""
    }
})
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="trans('New WhatsApp chat')"
        :style="{ width: '90vw', maxWidth: '440px' }" :breakpoints="{ '640px': '95vw' }">
        <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ trans("Customer / Phone number") }}</label>
                <PureMultiselectInfiniteScroll :key="shopId" v-model="customerId" :fetchRoute="fetchRoute"
                    valueProp="id" labelProp="name" labelAdditionalProp="reference"
                    :placeholder="trans('Search customer or type +phone number')"
                    :noOptionsText="trans('No customer found')"
                    @selectedObject="onSelectCustomer" @optionsList="onOptionsList"
                    @searchChange="onSearchChange">
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

            <p v-if="phoneError" class="text-xs text-red-500 leading-snug">{{ phoneError }}</p>

            <div v-if="showNewContact" class="flex flex-col gap-1">
                <p class="text-xs text-gray-500 leading-snug">
                    {{ trans("No customer found with this number. A new contact will be created.") }}
                </p>
                <label class="text-xs font-medium text-gray-600">{{ trans("Contact name") }}</label>
                <input v-model="contactName" type="text" :placeholder="trans('Optional')"
                    class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 text-gray-700" />
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
                <button type="button" :disabled="!canStart" @click="startChat"
                    class="px-3 py-1.5 text-sm text-white rounded-lg"
                    :class="!canStart ? 'bg-gray-300 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700'">
                    {{ submitting ? trans("Starting...") : trans("Start chat") }}
                </button>
            </div>
        </div>
    </Dialog>
</template>
