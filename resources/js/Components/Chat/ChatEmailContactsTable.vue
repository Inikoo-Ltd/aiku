<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faPencil, faPlus, faTimes, faTrashAlt } from "@fal"

library.add(faCheck, faPencil, faPlus, faTimes, faTrashAlt)

type Contact = {
    id: number
    name: string
    email: string
    company: string | null
    note: string | null
    all_shops: boolean
}

const props = withDefaults(defineProps<{
    data: any
    name?: string
    organisation: string
    shopId: number
    canManageShop: boolean
    canManageAllShops: boolean
}>(), { name: "email_contacts" })

const canManage = (contact: Contact) => contact.all_shops ? props.canManageAllShops : props.canManageShop

const isOpen = ref(false)
const editing = ref<Contact | null>(null)
const form = ref({ name: "", email: "", company: "", note: "", all_shops: false })
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)

const confirmingDeleteId = ref<number | null>(null)
const deletingId = ref<number | null>(null)
const rowError = ref<{ id: number; message: string } | null>(null)

const reload = () => router.reload({ only: [props.name] })

const openForm = (contact: Contact | null = null) => {
    editing.value = contact
    form.value = {
        name: contact?.name ?? "",
        email: contact?.email ?? "",
        company: contact?.company ?? "",
        note: contact?.note ?? "",
        all_shops: contact?.all_shops ?? false,
    }
    errors.value = {}
    isOpen.value = true
}

const save = async () => {
    isSaving.value = true
    errors.value = {}

    try {
        if (editing.value) {
            await axios.patch(route("grp.org.chat.agents.email_contacts.update", [props.organisation, editing.value.id]), {
                name: form.value.name,
                email: form.value.email,
                company: form.value.company || null,
                note: form.value.note || null,
            })
        } else {
            await axios.post(route("grp.org.chat.agents.email_contacts.store", [props.organisation, props.shopId]), {
                ...form.value,
                company: form.value.company || null,
                note: form.value.note || null,
            })
        }
        isOpen.value = false
        reload()
    } catch (error: any) {
        const fieldErrors = error?.response?.data?.errors ?? {}
        errors.value = Object.fromEntries(Object.entries(fieldErrors).map(([field, messages]: [string, any]) => [field, Array.isArray(messages) ? messages[0] : String(messages)]))

        if (!Object.keys(errors.value).length) {
            errors.value = { general: error?.response?.data?.message || ctrans("The contact could not be saved") }
        }
    } finally {
        isSaving.value = false
    }
}

const remove = async (contact: Contact) => {
    if (confirmingDeleteId.value !== contact.id) {
        confirmingDeleteId.value = contact.id
        rowError.value = null
        return
    }

    deletingId.value = contact.id
    try {
        await axios.delete(route("grp.org.chat.agents.email_contacts.delete", [props.organisation, contact.id]))
        reload()
    } catch (error: any) {
        rowError.value = { id: contact.id, message: error?.response?.data?.message || ctrans("The contact could not be removed") }
    } finally {
        deletingId.value = null
        confirmingDeleteId.value = null
    }
}
</script>

<template>
    <div>
    <Table :resource="data" :name="name">
        <template #add-on-button>
            <Button v-if="canManageShop || canManageAllShops" type="tertiary" :label="ctrans('Add contact')" @click="openForm()">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>

        <template #cell(email)="{ item }">
            <span class="whitespace-nowrap">{{ item.email }}</span>
        </template>

        <template #cell(all_shops)="{ item }">
            <span class="whitespace-nowrap text-sm text-gray-600">{{ item.all_shops ? ctrans("Every shop") : ctrans("This shop") }}</span>
        </template>

        <template #cell(actions)="{ item }">
            <div v-if="canManage(item)" class="flex items-center justify-end gap-1">
                <button type="button" v-tooltip="ctrans('Edit')" :aria-label="ctrans('Edit')"
                    class="flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                    @click="openForm(item)">
                    <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                </button>
                <button type="button" :disabled="deletingId === item.id" :aria-label="ctrans('Remove this saved contact')"
                    v-tooltip="confirmingDeleteId === item.id ? undefined : ctrans('Remove')"
                    class="flex h-7 items-center gap-1 rounded px-1.5 text-xs"
                    :class="confirmingDeleteId === item.id ? 'text-red-600 hover:bg-red-50' : 'text-gray-400 hover:bg-gray-100 hover:text-red-500'"
                    @click="remove(item)" @blur="confirmingDeleteId = null">
                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                    <span v-if="confirmingDeleteId === item.id">{{ ctrans("Remove?") }}</span>
                </button>
            </div>
            <p v-if="rowError?.id === item.id" class="mt-1 text-right text-xs text-red-600">{{ rowError.message }}</p>
        </template>
    </Table>

    <Dialog v-model:visible="isOpen" modal :header="editing ? ctrans('Edit saved contact') : ctrans('Add saved contact')"
        :style="{ width: '28rem' }" :breakpoints="{ '640px': '95vw' }">
        <form class="flex flex-col gap-3" @submit.prevent="save">
            <div class="flex flex-col gap-1">
                <label for="contact-name" class="text-xs font-medium text-gray-600">{{ ctrans("Name") }}</label>
                <InputText id="contact-name" v-model="form.name" fluid :placeholder="ctrans('For example DPD collections')" />
                <p v-if="errors.name" class="text-xs text-red-500">{{ errors.name }}</p>
            </div>
            <div class="flex flex-col gap-1">
                <label for="contact-email" class="text-xs font-medium text-gray-600">{{ ctrans("Email") }}</label>
                <InputText id="contact-email" v-model="form.email" type="email" fluid />
                <p v-if="errors.email" class="text-xs text-red-500">{{ errors.email }}</p>
            </div>
            <div class="flex flex-col gap-1">
                <label for="contact-company" class="text-xs font-medium text-gray-600">{{ ctrans("Company") }}</label>
                <InputText id="contact-company" v-model="form.company" fluid />
                <p v-if="errors.company" class="text-xs text-red-500">{{ errors.company }}</p>
            </div>
            <div class="flex flex-col gap-1">
                <label for="contact-note" class="text-xs font-medium text-gray-600">{{ ctrans("Note") }}</label>
                <Textarea id="contact-note" v-model="form.note" rows="2" auto-resize fluid :placeholder="ctrans('Shown when the contact is picked, for example what to send them')" />
                <p v-if="errors.note" class="text-xs text-red-500">{{ errors.note }}</p>
            </div>
            <label v-if="!editing && canManageAllShops" class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                <Checkbox v-model="form.all_shops" binary />
                {{ ctrans("Offer it on every shop of the organisation") }}
            </label>
            <p v-if="errors.general" class="text-xs text-red-500">{{ errors.general }}</p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="isOpen = false">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" :label="ctrans('Save')" :loading="isSaving" :disabled="!form.name.trim() || !form.email.trim()" @click="save">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </form>
    </Dialog>
    </div>
</template>
