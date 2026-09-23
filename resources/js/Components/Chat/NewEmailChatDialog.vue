<script setup lang="ts">
import { ref, computed, watch, toRef } from "vue"
import { useForm } from "@inertiajs/vue3"
import axios from "axios"
import Dialog from "primevue/dialog"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import ChatFormattingToolbar from "@/Components/Chat/ChatFormattingToolbar.vue"
import { ctrans } from "@/Composables/useTrans"
import { useComposerDraft } from "@/Composables/useComposerDraft"
import { routeType } from "@/types/route"

const visible = defineModel<boolean>("visible", { required: true })

const props = defineProps<{
    shopId: number | null
}>()

const customerId = ref<number | null>(null)
const selectedCustomer = ref<any | null>(null)
const isNotACustomer = ref(false)
const existingProspect = ref<{ name: string | null, company_name: string | null, owner: string | null } | null>(null)
let prospectLookupTimer: ReturnType<typeof setTimeout> | undefined

const messageTextarea = ref<HTMLTextAreaElement | null>(null)

const form = useForm({
    email: "",
    save_as_prospect: false,
    contact_name: "",
    company_name: "",
    subject: "",
    message: "",
})

const newEmailDraftKey = (field: string) => () => `new-email:${props.shopId ?? "none"}:${field}`
useComposerDraft(newEmailDraftKey("subject"), toRef(form, "subject"))
useComposerDraft(newEmailDraftKey("message"), toRef(form, "message"))

const fetchRoute = computed<routeType>(() => ({
    name: "grp.json.shop.customers",
    parameters: {
        shop: props.shopId,
        "filter[has_email]": 1,
    },
}))

const canSend = computed(
    () => (isNotACustomer.value || !!selectedCustomer.value) && !!form.email.trim() && !!form.subject.trim() && !!form.message.trim()
)

watch(selectedCustomer, (customer) => {
    form.email = customer?.email ?? ""
})

watch(() => form.email, (email) => {
    clearTimeout(prospectLookupTimer)
    existingProspect.value = null
    if (!isNotACustomer.value || !props.shopId || !/^\S+@\S+\.\S+$/.test(email.trim())) {
        return
    }
    prospectLookupTimer = setTimeout(async () => {
        const { data } = await axios.get(route("grp.json.shop.prospect_by_email", { shop: props.shopId }), { params: { email: email.trim() } })
        if (form.email === email) {
            existingProspect.value = data.prospect
            if (data.prospect) {
                form.save_as_prospect = false
            }
        }
    }, 400)
})

watch(isNotACustomer, () => {
    customerId.value = null
    selectedCustomer.value = null
    form.email = ""
    form.save_as_prospect = false
})

const send = () => {
    if (!canSend.value) {
        return
    }

    const target = isNotACustomer.value
        ? route("grp.models.shop.email_chat.store", { shop: props.shopId })
        : route("grp.models.customer.email_chat.store", { customer: selectedCustomer.value.id })

    form.post(target, {
        onSuccess: () => {
            form.reset("subject", "message")
            visible.value = false
        },
    })
}

watch(visible, (isVisible) => {
    if (!isVisible) {
        isNotACustomer.value = false
        customerId.value = null
        selectedCustomer.value = null
        form.reset("email", "save_as_prospect", "contact_name", "company_name")
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

            <div v-if="!isNotACustomer" class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("Customer") }}</label>
                <PureMultiselectInfiniteScroll :key="shopId" v-model="customerId" :fetchRoute="fetchRoute"
                    valueProp="id" labelProp="name" labelAdditionalProp="reference"
                    :placeholder="ctrans('Search customer by name, reference or email')"
                    :noOptionsText="ctrans('No customer found. Search by their name or business, or write to an email address below')"
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

            <button type="button" class="self-start text-xs text-indigo-600 hover:underline"
                @click="isNotACustomer = !isNotACustomer">
                {{ isNotACustomer ? ctrans("Write to a customer instead") : ctrans("Not a customer yet? Write to an email address") }}
            </button>

            <div v-if="selectedCustomer || isNotACustomer" class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("To") }}</label>
                <PureInput v-model="form.email" type="email" :placeholder="ctrans('Email address')" />
            </div>

            <p v-if="isNotACustomer && existingProspect" class="text-xs text-gray-600 leading-snug">
                {{ ctrans("Already a prospect") }}: {{ existingProspect.name ?? existingProspect.company_name }}
                <span v-if="existingProspect.company_name && existingProspect.name">({{ existingProspect.company_name }})</span>
                <span v-if="existingProspect.owner">· {{ ctrans("brought in by :name", { name: existingProspect.owner }) }}</span>
            </p>

            <div v-else-if="isNotACustomer" class="flex flex-col gap-2">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input v-model="form.save_as_prospect" type="checkbox" class="h-4 w-4 rounded cursor-pointer" />
                    {{ ctrans("Save them as a prospect, credited to you") }}
                </label>
                <template v-if="form.save_as_prospect">
                    <PureInput v-model="form.contact_name" :placeholder="ctrans('Contact name')" />
                    <PureInput v-model="form.company_name" :placeholder="ctrans('Business name')" />
                </template>
            </div>

            <PureInput v-model="form.subject" :placeholder="ctrans('Subject')" />
            <div :ref="(el: any) => (messageTextarea = el?.querySelector('textarea') ?? null)">
                <ChatFormattingToolbar :textarea="messageTextarea" allow-underline class="mb-1" />
                <PureTextarea v-model="form.message" :rows="8" :placeholder="ctrans('Message')" />
            </div>

            <p v-if="form.errors.email" class="text-xs text-red-500 leading-snug">{{ form.errors.email }}</p>
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
