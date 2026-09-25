<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import MultiSelect from 'primevue/multiselect'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Table from '@/Components/Table/Table.vue'
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import EditorV2 from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { capitalize } from '@/Composables/capitalize'
import { useFormatTime } from '@/Composables/useFormatTime'
import { ctrans } from '@/Composables/useTrans'
import { PageHeadingTypes } from '@/types/PageHeading'
import { routeType } from '@/types/route'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faMailBulk, faPlus, faPaperclip, faTimes } from '@fal'

library.add(faMailBulk, faPlus, faPaperclip, faTimes)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: any
    sendRoute: routeType
    employeeOptions: { value: number; label: string }[]
}>()

const editorToolbar = [
    'heading1', 'heading2', 'heading3', 'bold', 'italic', 'underline', 'bulletList', 'orderedList',
    'blockquote', 'divider', 'color', 'highlight', 'link', 'alignLeft', 'alignCenter', 'alignRight', 'clear', 'undo', 'redo',
]

const showComposeModal = ref(false)
const previewEmail = ref<{ subject: string; body: string; attachments: string[] } | null>(null)

const form = useForm<{
    subject: string
    body: string
    employee_ids: number[]
    attachments: File[]
}>({
    subject: '',
    body: '',
    employee_ids: [],
    attachments: [],
})

const addAttachments = (event: Event) => {
    const input = event.target as HTMLInputElement
    form.attachments = [...form.attachments, ...Array.from(input.files ?? [])]
    input.value = ''
}

const removeAttachment = (index: number) => {
    form.attachments = form.attachments.filter((_, fileIndex) => fileIndex !== index)
}

const attachmentError = () => form.errors.attachments ?? Object.entries(form.errors).find(([key]) => key.startsWith('attachments.'))?.[1]

const openComposeModal = () => {
    form.reset()
    form.clearErrors()
    showComposeModal.value = true
}

const submitSend = (sendRoute: routeType) => {
    form.post(route(sendRoute.name, sendRoute.parameters), {
        preserveScroll: true,
        onSuccess: () => {
            showComposeModal.value = false
            form.reset()
        },
    })
}
</script>

<template layout="Grp">
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-new-bulk-email="{ action }">
            <Button type="create" :icon="action.icon" :label="action.label" @click="openComposeModal" />
        </template>
    </PageHeading>

    <Modal :isOpen="showComposeModal" @onClose="showComposeModal = false" width="w-full max-w-3xl">
        <form class="space-y-4" @submit.prevent="submitSend(sendRoute)">
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ ctrans('Recipients') }}</label>
                <MultiSelect
                    v-model="form.employee_ids"
                    :options="employeeOptions"
                    optionLabel="label"
                    optionValue="value"
                    filter
                    :maxSelectedLabels="5"
                    class="mt-1 w-full"
                    :placeholder="ctrans('All working employees')"
                />
                <div v-if="form.errors.employee_ids" class="mt-1 text-xs text-red-600">{{ form.errors.employee_ids }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ ctrans('Subject') }}</label>
                <input
                    v-model="form.subject"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[--app-accent] focus:ring-[--app-accent] sm:text-sm"
                />
                <div v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ ctrans('Message') }}</label>
                <div class="mt-1 min-h-[16rem] rounded-md border border-gray-300 p-3">
                    <EditorV2 v-model="form.body" :toggle="editorToolbar" :placeholder="ctrans('Write your message')" />
                </div>
                <div v-if="form.errors.body" class="mt-1 text-xs text-red-600">{{ form.errors.body }}</div>
            </div>

            <div>
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 hover:text-gray-900">
                    <FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
                    {{ ctrans('Attach files') }}
                    <input type="file" multiple class="sr-only" @change="addAttachments" />
                </label>
                <span class="ml-2 text-xs text-gray-500">{{ ctrans('Up to 5 files, 5 MB each') }}</span>
                <ul v-if="form.attachments.length" class="mt-2 flex flex-wrap gap-2">
                    <li v-for="(file, index) in form.attachments" :key="index" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700">
                        {{ file.name }}
                        <button type="button" class="text-gray-400 hover:text-red-600" :aria-label="ctrans('Remove :name', { name: file.name })" @click="removeAttachment(index)">
                            <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <div v-if="attachmentError()" class="mt-1 text-xs text-red-600">{{ attachmentError() }}</div>
            </div>

            <div class="flex justify-end gap-3">
                <Button type="secondary" size="sm" :label="ctrans('Cancel')" @click.prevent="showComposeModal = false" />
                <Button
                    type="create"
                    size="sm"
                    icon="fal fa-mail-bulk"
                    :label="form.employee_ids.length ? ctrans('Send to :count employees', { count: form.employee_ids.length }) : ctrans('Send to all working employees')"
                    nativeType="submit"
                    :loading="form.processing"
                />
            </div>
        </form>
    </Modal>

    <Modal :isOpen="!!previewEmail" @onClose="previewEmail = null" width="w-full max-w-3xl">
        <div v-if="previewEmail">
            <h3 class="mb-4 text-lg font-semibold">{{ previewEmail.subject }}</h3>
            <div class="prose max-w-none" v-html="previewEmail.body" />
            <ul v-if="previewEmail.attachments?.length" class="mt-4 space-y-1 border-t pt-3 text-sm text-gray-600">
                <li v-for="name in previewEmail.attachments" :key="name">
                    <FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" /> {{ name }}
                </li>
            </ul>
        </div>
    </Modal>

    <Table :resource="data" class="mt-5">
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.created_at, { formatTime: 'hm' }) }}</span>
        </template>

        <template #cell(subject)="{ item }">
            <button type="button" class="text-left primaryLink" @click="previewEmail = item">{{ item.subject }}</button>
        </template>

        <template #cell(attachments)="{ item }">
            <span v-if="item.attachments?.length" class="whitespace-nowrap" v-tooltip="item.attachments.join(', ')">
                <FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" /> {{ item.attachments.length }}
            </span>
        </template>

        <template #cell(sender_name)="{ item }">
            {{ item.sender_name || '—' }}
        </template>
    </Table>
</template>
