<script setup lang="ts">
import { ref, computed, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import ChatFormattingToolbar from "@/Components/Chat/ChatFormattingToolbar.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const visible = defineModel<boolean>("visible", { required: true })

const props = defineProps<{
    shopId: number | null
}>()

const customerId = ref<number | null>(null)
const selectedCustomer = ref<any | null>(null)

const messageTextarea = ref<HTMLTextAreaElement | null>(null)

const form = useForm({
    subject: "",
    message: "",
})

const fetchRoute = computed<routeType>(() => ({
    name: "grp.json.shop.customers",
    parameters: {
        shop: props.shopId,
        "filter[has_email]": 1,
    },
}))

const canSend = computed(
    () => !!selectedCustomer.value?.email && !!form.subject.trim() && !!form.message.trim()
)

const send = () => {
    if (!canSend.value) {
        return
    }

    form.post(route("grp.models.customer.email_chat.store", { customer: selectedCustomer.value.id }), {
        onSuccess: () => {
            visible.value = false
        },
    })
}

watch(visible, (isVisible) => {
    if (!isVisible) {
        customerId.value = null
        selectedCustomer.value = null
        form.reset()
        form.clearErrors()
    }
})
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('New email')"
        :style="{ width: '90vw', maxWidth: '560px' }" :breakpoints="{ '640px': '95vw' }">
        <div class="flex flex-col gap-3">
            <p class="text-xs text-gray-500 leading-snug">
                {{ ctrans("It opens a conversation in this inbox, and their reply comes back to it.") }}
            </p>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("Customer") }}</label>
                <PureMultiselectInfiniteScroll :key="shopId" v-model="customerId" :fetchRoute="fetchRoute"
                    valueProp="id" labelProp="name" labelAdditionalProp="reference"
                    :placeholder="ctrans('Search customer by name, reference or email')"
                    :noOptionsText="ctrans('No customer with an email address found')"
                    @selectedObject="(customer: any) => selectedCustomer = customer ?? null">
                    <template #singlelabel="{ value }">
                        <div class="w-full text-left pl-4 leading-4 truncate mr-2">
                            {{ value.name }}
                            <span class="text-sm text-gray-400">({{ value.reference }} · {{ value.email }})</span>
                        </div>
                    </template>

                    <template #option="{ option }">
                        <div>
                            {{ option.name }}
                            <span class="text-sm text-gray-400">({{ option.reference }} · {{ option.email }})</span>
                        </div>
                    </template>
                </PureMultiselectInfiniteScroll>
            </div>

            <PureInput v-model="form.subject" :placeholder="ctrans('Subject')" />
            <div :ref="(el: any) => (messageTextarea = el?.querySelector('textarea') ?? null)">
                <ChatFormattingToolbar :textarea="messageTextarea" allow-underline class="mb-1" />
                <PureTextarea v-model="form.message" :rows="8" :placeholder="ctrans('Message')" />
            </div>

            <p v-if="form.errors.subject" class="text-xs text-red-500 leading-snug">{{ form.errors.subject }}</p>
            <p v-if="form.errors.message" class="text-xs text-red-500 leading-snug">{{ form.errors.message }}</p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" class="px-3 py-1.5 text-sm text-gray-600 rounded-lg hover:bg-gray-100"
                    @click="visible = false">
                    {{ ctrans("Cancel") }}
                </button>
                <button type="button" :disabled="!canSend || form.processing" @click="send"
                    class="px-3 py-1.5 text-sm text-white rounded-lg"
                    :class="!canSend || form.processing ? 'bg-gray-300 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700'">
                    {{ form.processing ? ctrans("Sending...") : ctrans("Send") }}
                </button>
            </div>
        </div>
    </Dialog>
</template>
