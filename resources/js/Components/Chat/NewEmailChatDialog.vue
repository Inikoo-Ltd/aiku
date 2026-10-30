<script setup lang="ts">
import { ref, computed, watch, toRef } from "vue"
import { useForm } from "@inertiajs/vue3"
import axios from "axios"
import Dialog from "primevue/dialog"
import Checkbox from "primevue/checkbox"
import Select from "primevue/select"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import ChatFormattingToolbar from "@/Components/Chat/ChatFormattingToolbar.vue"
import ChatMessageEditor from "@/Components/Chat/ChatMessageEditor.vue"
import PolishMessageButton from "@/Components/Chat/PolishMessageButton.vue"
import EditorWritingQualityCheck from "@/Components/WritingQuality/EditorWritingQualityCheck.vue"
import EmailAttachmentPicker from "@/Components/Chat/EmailAttachmentPicker.vue"
import { ctrans } from "@/Composables/useTrans"
import { useComposerDraft } from "@/Composables/useComposerDraft"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAddressBook, faPaperPlane, faTimes, faTrashAlt } from "@fal"
library.add(faAddressBook, faPaperPlane, faTimes, faTrashAlt)

type SavedContact = {
    id: number
    name: string
    email: string
    company: string | null
    note: string | null
    all_shops: boolean
    can_manage?: boolean
}

const visible = defineModel<boolean>("visible", { required: true })

const props = defineProps<{
    shopId: number | null
    shops?: Array<{ id: number; code?: string; name: string }>
    toAddress?: boolean
}>()

// With several shops picked on the rail the email still goes out from one mailbox: it is chosen here.
const pickedShopId = ref<number | null>(props.shopId)
const currentShopId = computed(() => pickedShopId.value ?? props.shopId)

const customerId = ref<number | null>(null)
const selectedCustomer = ref<any | null>(null)
const isNotACustomer = ref(false)
const customerSearch = ref("")
const existingProspect = ref<{ name: string | null, company_name: string | null, owner: string | null } | null>(null)
let prospectLookupTimer: ReturnType<typeof setTimeout> | undefined
const savedContacts = ref<SavedContact[]>([])
const pickedContact = ref<SavedContact | null>(null)
let savedContactsTimer: ReturnType<typeof setTimeout> | undefined
const currentOrganisation = String((route().params as Record<string, any>)?.organisation ?? "aw")

const messageEditor = ref<InstanceType<typeof ChatMessageEditor> | null>(null)

const form = useForm({
    email: "",
    save_as_prospect: false,
    save_as_contact: false,
    contact_name: "",
    company_name: "",
    subject: "",
    message: "",
    attachments: [] as File[],
})

const newEmailDraftKey = (field: string) => () => `new-email:${currentShopId.value ?? "none"}:${field}`
const clearSubjectDraft = useComposerDraft(newEmailDraftKey("subject"), toRef(form, "subject"))
const clearMessageDraft = useComposerDraft(newEmailDraftKey("message"), toRef(form, "message"))

const fetchRoute = computed<routeType>(() => ({
    name: "grp.json.shop.customers",
    parameters: {
        shop: currentShopId.value,
        "filter[has_email]": 1,
    },
}))

const searchIsEmail = computed(() => /^\S+@\S+\.\S+$/.test(customerSearch.value.trim()))

const firstAddress = computed(() => (form.email.split(/[\s,;]+/).find(Boolean) ?? "").trim().toLowerCase())

const savedContactsQuery = computed(() => {
    if (!isNotACustomer.value) {
        return customerSearch.value.trim()
    }
    return /[\s,;]/.test(form.email.trim()) ? null : form.email.trim()
})

const isSavedContact = computed(
    () => !!firstAddress.value && savedContacts.value.some((contact) => contact.email.toLowerCase() === firstAddress.value)
)

const visibleSavedContacts = computed(() => {
    if (pickedContact.value || savedContactsQuery.value === null) {
        return []
    }
    if (!isNotACustomer.value && (selectedCustomer.value || !savedContactsQuery.value)) {
        return []
    }
    return savedContacts.value.filter((contact) => contact.email.toLowerCase() !== firstAddress.value)
})

watch([savedContactsQuery, visible], ([search, isVisible]) => {
    clearTimeout(savedContactsTimer)
    if (!isVisible || !currentShopId.value || search === null || (!isNotACustomer.value && !search)) {
        return
    }
    savedContactsTimer = setTimeout(async () => {
        const { data } = await axios.get(
            route("grp.org.chat.agents.email_contacts.search", [currentOrganisation, currentShopId.value]),
            { params: { query: search } }
        )
        if (savedContactsQuery.value === search) {
            savedContacts.value = data.data
        }
    }, 250)
})

const confirmingDeleteContactId = ref<number | null>(null)
const deletingContactId = ref<number | null>(null)
const contactError = ref<string | null>(null)

const removeContact = async (contact: SavedContact) => {
    if (confirmingDeleteContactId.value !== contact.id) {
        confirmingDeleteContactId.value = contact.id
        contactError.value = null
        return
    }

    deletingContactId.value = contact.id
    try {
        await axios.delete(route("grp.org.chat.agents.email_contacts.delete", [currentOrganisation, contact.id]))
        savedContacts.value = savedContacts.value.filter((other) => other.id !== contact.id)
    } catch (error: any) {
        contactError.value = error?.response?.data?.message || ctrans("The contact could not be removed")
    } finally {
        deletingContactId.value = null
        confirmingDeleteContactId.value = null
    }
}

const pickContact = (contact: SavedContact) => {
    pickedContact.value = contact
    form.contact_name = contact.name
    form.company_name = contact.company ?? ""
    form.save_as_contact = false
    if (isNotACustomer.value) {
        form.email = contact.email
    } else {
        isNotACustomer.value = true
    }
}

const canSend = computed(
    () => (isNotACustomer.value || !!selectedCustomer.value) && !!form.email.trim() && !!form.subject.trim() && !!form.message.trim()
)

watch(selectedCustomer, (customer) => {
    form.email = customer?.email ?? ""
})

watch(() => form.email, (addresses) => {
    const email = addresses.split(/[\s,;]+/).find(Boolean) ?? ""
    if (pickedContact.value && email.trim().toLowerCase() !== pickedContact.value.email.toLowerCase()) {
        pickedContact.value = null
        form.contact_name = ""
        form.company_name = ""
    }
    clearTimeout(prospectLookupTimer)
    existingProspect.value = null
    if (!isNotACustomer.value || !currentShopId.value || !/^\S+@\S+\.\S+$/.test(email.trim())) {
        return
    }
    prospectLookupTimer = setTimeout(async () => {
        const { data } = await axios.get(route("grp.json.shop.prospect_by_email", { shop: currentShopId.value }), { params: { email: email.trim() } })
        if (form.email === addresses) {
            existingProspect.value = data.prospect
            if (data.prospect) {
                form.save_as_prospect = false
            }
        }
    }, 400)
})

watch(isNotACustomer, (toAddress) => {
    if (!toAddress) {
        pickedContact.value = null
    }
    customerId.value = null
    selectedCustomer.value = null
    form.email = pickedContact.value?.email ?? (searchIsEmail.value ? customerSearch.value.trim() : "")
    form.save_as_prospect = false
    form.save_as_contact = false
})

const send = () => {
    if (!canSend.value) {
        return
    }

    const target = isNotACustomer.value
        ? route("grp.models.shop.email_chat.store", { shop: currentShopId.value })
        : route("grp.models.customer.email_chat.store", { customer: selectedCustomer.value.id })

    form.transform((data) => ({
        ...data,
        save_as_contact: isNotACustomer.value && !pickedContact.value && !isSavedContact.value && data.save_as_contact,
    })).post(target, {
        onSuccess: () => {
            clearSubjectDraft()
            clearMessageDraft()
            form.reset("subject", "message", "attachments")
            visible.value = false
        },
    })
}

watch(pickedShopId, () => {
    customerId.value = null
    selectedCustomer.value = null
    pickedContact.value = null
    savedContacts.value = []
})

watch(visible, (isVisible) => {
    if (isVisible) {
        pickedShopId.value = props.shopId ?? props.shops?.[0]?.id ?? null
        isNotACustomer.value = !!props.toAddress
    } else {
        isNotACustomer.value = false
        customerSearch.value = ""
        customerId.value = null
        selectedCustomer.value = null
        pickedContact.value = null
        savedContacts.value = []
        form.reset("email", "save_as_prospect", "save_as_contact", "contact_name", "company_name")
        form.clearErrors()
    }
})
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('New email')"
        :style="{ width: '90vw', maxWidth: '900px' }" :breakpoints="{ '640px': '95vw' }">
        <div class="flex flex-col gap-3">
            <p class="text-xs text-gray-500 leading-snug">
                {{ ctrans("It opens a conversation in this inbox, and their reply comes back to it.") }}
            </p>

            <div v-if="(shops?.length ?? 0) > 1" class="flex flex-col gap-1">
                <label for="new-email-shop" class="text-xs font-medium text-gray-600">{{ ctrans("From") }}</label>
                <Select v-model="pickedShopId" input-id="new-email-shop" :options="shops" option-label="name" option-value="id" fluid>
                    <template #option="{ option }">
                        <span v-if="option.code" class="mr-2 whitespace-nowrap font-mono text-xs text-gray-500">{{ option.code }}</span>
                        <span>{{ option.name }}</span>
                    </template>
                </Select>
            </div>

            <div v-if="!isNotACustomer" class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("Customer") }}</label>
                <PureMultiselectInfiniteScroll :key="currentShopId ?? 0" v-model="customerId" :fetchRoute="fetchRoute"
                    valueProp="id" labelProp="name" labelAdditionalProp="reference"
                    :placeholder="ctrans('Search customer by name, reference or email')"
                    @selectedObject="(customer: any) => { selectedCustomer = customer ?? null; if (customer) customerSearch = '' }"
                    @searchChange="(search: string) => customerSearch = search || customerSearch">
                    <template #singlelabel="{ value }">
                        <div class="w-full text-left pl-4 leading-4 truncate mr-2">
                            {{ value.name }}
                            <span class="text-sm text-gray-400">({{ value.reference }} · {{ value.email }})</span>
                        </div>
                    </template>

                    <template #option="{ option }">
                        <div class="grid w-full grid-cols-[minmax(0,1fr)_5.5rem_minmax(0,1fr)] items-baseline gap-3 text-sm">
                            <span class="truncate" :title="option.name">{{ option.name }}</span>
                            <span class="font-mono text-xs text-gray-500 tabular-nums">{{ option.reference }}</span>
                            <span class="truncate text-gray-500" :title="option.email">{{ option.email }}</span>
                        </div>
                    </template>

                    <template #nooptions>
                        <button v-if="searchIsEmail" type="button" class="text-left font-medium text-gray-700 hover:underline"
                            @mousedown.prevent="isNotACustomer = true">
                            {{ ctrans("Send to :email", { email: customerSearch.trim() }) }}
                        </button>
                        <template v-else>{{ ctrans("No customer found") }}</template>
                    </template>
                </PureMultiselectInfiniteScroll>
            </div>

            <div v-if="visibleSavedContacts.length" class="flex flex-col gap-1">
                <span class="text-xs font-medium text-gray-600">{{ ctrans("Saved contacts") }}</span>
                <ul class="max-h-48 overflow-y-auto rounded-md border border-gray-200 divide-y divide-gray-100">
                    <li v-for="contact in visibleSavedContacts" :key="contact.id" class="group/contact flex items-center">
                        <button type="button"
                            class="grid min-w-0 flex-1 grid-cols-[minmax(0,1fr)_minmax(0,1fr)] items-baseline gap-3 px-3 py-1.5 text-left text-sm hover:bg-[--app-accent-soft] focus:outline-none focus-visible:ring-1 focus-visible:ring-[--app-accent]"
                            @mousedown.prevent="pickContact(contact)">
                            <span class="truncate" :title="contact.name">
                                <FontAwesomeIcon icon="fal fa-address-book" class="mr-1 text-gray-400" fixed-width aria-hidden="true" />
                                {{ contact.name }}
                                <span v-if="contact.company" class="text-gray-400">· {{ contact.company }}</span>
                            </span>
                            <span class="truncate text-gray-500" :title="contact.email">{{ contact.email }}</span>
                        </button>
                        <button v-if="contact.can_manage" type="button" :disabled="deletingContactId === contact.id"
                            class="mr-1 flex h-7 shrink-0 items-center gap-1 rounded px-1.5 text-xs transition-colors"
                            :class="confirmingDeleteContactId === contact.id ? 'text-red-600 hover:bg-red-50' : 'text-gray-400 opacity-0 hover:bg-gray-100 hover:text-red-500 focus:opacity-100 group-hover/contact:opacity-100'"
                            v-tooltip="confirmingDeleteContactId === contact.id ? undefined : ctrans('Remove this saved contact')"
                            :aria-label="ctrans('Remove this saved contact')"
                            @mousedown.prevent="removeContact(contact)" @blur="confirmingDeleteContactId = null">
                            <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                            <span v-if="confirmingDeleteContactId === contact.id">{{ ctrans("Remove?") }}</span>
                        </button>
                    </li>
                </ul>
                <p v-if="contactError" class="text-xs text-red-500 leading-snug">{{ contactError }}</p>
            </div>

            <button type="button" class="self-start text-xs font-medium text-gray-700 hover:underline"
                @click="isNotACustomer = !isNotACustomer">
                {{ isNotACustomer ? ctrans("Choose a customer instead") : ctrans("Send to an email address instead") }}
            </button>

            <div v-if="selectedCustomer || isNotACustomer" class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("To") }}</label>
                <PureInput v-model="form.email" :placeholder="ctrans('Email addresses, separated by commas')" />
                <p v-if="pickedContact" class="text-xs text-gray-600 leading-snug">
                    {{ ctrans("Saved contact") }}: {{ pickedContact.name }}
                    <span v-if="pickedContact.company">({{ pickedContact.company }})</span>
                    <span v-if="pickedContact.note" class="text-gray-400">· {{ pickedContact.note }}</span>
                </p>
            </div>

            <p v-if="isNotACustomer && existingProspect" class="text-xs text-gray-600 leading-snug">
                {{ ctrans("Already a prospect") }}: {{ existingProspect.name ?? existingProspect.company_name }}
                <span v-if="existingProspect.company_name && existingProspect.name">({{ existingProspect.company_name }})</span>
                <span v-if="existingProspect.owner">· {{ ctrans("brought in by :name", { name: existingProspect.owner }) }}</span>
            </p>

            <div v-if="isNotACustomer && !pickedContact" class="flex flex-col gap-2">
                <label v-if="!existingProspect" class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <Checkbox v-model="form.save_as_prospect" binary />
                    {{ ctrans("Save them as a prospect, credited to you") }}
                </label>
                <label v-if="!isSavedContact" class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <Checkbox v-model="form.save_as_contact" binary />
                    {{ ctrans("Save as a contact for the next email, such as a carrier or the accountants") }}
                </label>
                <template v-if="form.save_as_prospect || (form.save_as_contact && !isSavedContact)">
                    <PureInput v-model="form.contact_name" :placeholder="ctrans('Contact name')" />
                    <PureInput v-model="form.company_name" :placeholder="ctrans('Business name')" />
                </template>
            </div>

            <PureInput v-model="form.subject" :placeholder="ctrans('Subject')" />
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <ChatFormattingToolbar :editor="messageEditor?.editor" allow-underline />
                    <div class="mx-1 h-5 w-px bg-gray-200" />
                    <PolishMessageButton v-model="form.message" channel="email" />
                    <EditorWritingQualityCheck compact :editor="messageEditor?.editor" context="customer_email" />
                </div>
                <ChatMessageEditor ref="messageEditor" v-model="form.message" :placeholder="ctrans('Message')" allow-underline
                    class="rounded-md border border-gray-300 px-3 py-2 focus-within:border-gray-500 [&_.ProseMirror]:min-h-64 [&_.ProseMirror]:max-h-[60vh]" />
            </div>
            <EmailAttachmentPicker v-model="form.attachments" :errors="form.errors" />

            <p v-if="form.errors.email" class="text-xs text-red-500 leading-snug">{{ form.errors.email }}</p>
            <p v-if="form.errors.subject" class="text-xs text-red-500 leading-snug">{{ form.errors.subject }}</p>
            <p v-if="form.errors.message" class="text-xs text-red-500 leading-snug">{{ form.errors.message }}</p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" :label="form.processing ? ctrans('Sending...') : ctrans('Send')" :loading="form.processing"
                    :disabled="!canSend || form.processing" @click="send">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-paper-plane" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </div>
    </Dialog>
</template>
