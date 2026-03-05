<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import Table from '@/Components/Table/Table.vue'
import Modal from '@/Components/Utils/Modal.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Toggle from '@/Components/Pure/Toggle.vue'
import { ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core";
import { faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faCalendarAlt, faPlus, faTrashAlt, faPencil } from "@fal";

library.add(faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faCalendarAlt, faPlus, faTrashAlt, faPencil)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: any
}>()

const showCreateModal = ref(false)
const showEditModal = ref(false)
const editingHolidayYear = ref<any | null>(null)
const toTouchedCreate = ref(false)
const toTouchedEdit = ref(false)

const form = useForm<{
    label: string
    start_date: string
    end_date: string
    is_active: boolean
}>({
    label: '',
    start_date: '',
    end_date: '',
    is_active: false,
})

const editForm = useForm<{
    label: string
    start_date: string
    end_date: string
    is_active: boolean
}>({
    label: '',
    start_date: '',
    end_date: '',
    is_active: false,
})

const openCreateModal = () => {
    form.reset()
    form.clearErrors()
    toTouchedCreate.value = false
    showCreateModal.value = true
}

const closeCreateModal = () => {
    showCreateModal.value = false
    form.clearErrors()
}

const submitCreate = () => {
    form.post(route('grp.org.hr.holiday_years.store', route().params), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            closeCreateModal()
        },
    })
}

const openEditModal = (holidayYear: any) => {
    editingHolidayYear.value = holidayYear
    editForm.clearErrors()
    toTouchedEdit.value = false
    editForm.label = holidayYear.label
    editForm.start_date = holidayYear.start_date
    editForm.end_date = holidayYear.end_date
    editForm.is_active = holidayYear.is_active
    showEditModal.value = true
}

const closeEditModal = () => {
    showEditModal.value = false
    editForm.clearErrors()
}

const submitEdit = () => {
    if (!editingHolidayYear.value) {
        return
    }

    editForm.patch(
        route('grp.org.hr.holiday_years.update', {
            ...route().params,
            holidayYear: editingHolidayYear.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                editForm.reset()
                editingHolidayYear.value = null
                closeEditModal()
            },
        }
    )
}

const toggleActive = (item: any) => {
    if (item.is_active) {
        return
    }

    useForm({}).patch(
        route('grp.org.hr.holiday_years.activate', {
            ...route().params,
            holidayYear: item.id,
        }),
        {
            preserveScroll: true,
        }
    )
}

</script>

<template layout="Grp">
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-create-holiday-year="{ action }">
            <Button
                type="create"
                size="xs"
                :icon="action.icon"
                :label="action.label"
                @click="openCreateModal"
            />
        </template>
    </PageHeading>

    <Modal :isOpen="showCreateModal" @onClose="closeCreateModal" width="w-full max-w-lg">
        <form class="space-y-4" @submit.prevent="submitCreate">
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Label') }}
                </label>
                <input
                    v-model="form.label"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    placeholder="e.g. UK 2026-2027"
                />
                <div v-if="form.errors.label" class="mt-1 text-xs text-red-600">
                    {{ form.errors.label }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Start Date') }}
                </label>
                <input
                    v-model="form.start_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div v-if="form.errors.start_date" class="mt-1 text-xs text-red-600">
                    {{ form.errors.start_date }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('End Date') }}
                </label>
                <input
                    v-model="form.end_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div v-if="form.errors.end_date" class="mt-1 text-xs text-red-600">
                    {{ form.errors.end_date }}
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="is_active"
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <label for="is_active" class="text-sm text-gray-700">
                    {{ trans('Set as active holiday year') }}
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <Button
                    type="secondary"
                    size="sm"
                    :label="trans('Cancel')"
                    @click.prevent="closeCreateModal"
                />
                <Button
                    type="create"
                    size="sm"
                    :label="trans('Save')"
                    :disabled="form.processing"
                />
            </div>
        </form>
    </Modal>

    <Modal :isOpen="showEditModal" @onClose="closeEditModal" width="w-full max-w-lg">
        <form class="space-y-4" @submit.prevent="submitEdit">
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Label') }}
                </label>
                <input
                    v-model="editForm.label"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div v-if="editForm.errors.label" class="mt-1 text-xs text-red-600">
                    {{ editForm.errors.label }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Start Date') }}
                </label>
                <input
                    v-model="editForm.start_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div v-if="editForm.errors.start_date" class="mt-1 text-xs text-red-600">
                    {{ editForm.errors.start_date }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('End Date') }}
                </label>
                <input
                    v-model="editForm.end_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div v-if="editForm.errors.end_date" class="mt-1 text-xs text-red-600">
                    {{ editForm.errors.end_date }}
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="edit_is_active"
                    v-model="editForm.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <label for="edit_is_active" class="text-sm text-gray-700">
                    {{ trans('Set as active holiday year') }}
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <Button
                    type="secondary"
                    size="sm"
                    :label="trans('Cancel')"
                    @click.prevent="closeEditModal"
                />
                <Button
                    type="create"
                    size="sm"
                    :label="trans('Save changes')"
                    :disabled="editForm.processing"
                />
            </div>
        </form>
    </Modal>

    <Table :resource="data" class="mt-5">
        <template #cell(label)="{ item }">
            <span class="whitespace-nowrap font-medium text-gray-900">
                {{ item.label }}
            </span>
        </template>

        <template #cell(start_date)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.start_date }}
            </span>
        </template>

        <template #cell(end_date)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.end_date }}
            </span>
        </template>

        <template #cell(is_active)="{ item }">
            <Toggle
                :modelValue="item.is_active"
                @update:modelValue="toggleActive(item)"
                :disabled="item.is_active"
            />
        </template>

        <template #cell(action)="{ item }">
            <div class="flex items-center gap-2">
                <Button
                    type="secondary"
                    size="xs"
                    icon="fal fa-pencil"
                    :label="trans('Edit')"
                    @click="openEditModal(item)"
                />
                <!--
                <ModalConfirmationDelete
                    :routeDelete="{
                        name: 'grp.org.hr.holiday_years.delete',
                        parameters: {
                            ...route().params,
                            holiday_year: item.id,
                        },
                    }"
                    :title="trans('Are you sure you want to delete this holiday year?')"
                    isFullLoading
                >
                    <template #default="{ changeModel }">
                        <Button
                            type="negative"
                            size="xs"
                            icon="fal fa-trash-alt"
                            :label="trans('Delete')"
                            @click="changeModel"
                        />
                    </template>
                </ModalConfirmationDelete>
                -->
            </div>
        </template>
    </Table>
</template>
