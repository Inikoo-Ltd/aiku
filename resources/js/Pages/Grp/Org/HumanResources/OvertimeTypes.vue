<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Table from '@/Components/Table/Table.vue'
import Modal from '@/Components/Utils/Modal.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core";
import { faTrash } from "@fal";

library.add(faTrash);
const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: any
    categoryOptions: { value: string; label: string }[]
    compensationTypeOptions: { value: string; label: string }[]
}>()

const showCreateModal = ref(false)
const isEditMode = ref(false)
const editingOvertimeTypeId = ref<number | null>(null)

const form = useForm<{
    code: string
    name: string
    description: string | null
    category: string
    compensation_type: string
    multiplier: number | null
    is_active: boolean
}>({
    code: '',
    name: '',
    description: null,
    category: '',
    compensation_type: '',
    multiplier: null,
    is_active: true,
})

const resetForm = () => {
    form.reset()
    form.clearErrors()
    isEditMode.value = false
    editingOvertimeTypeId.value = null
}

const openModal = () => {
    resetForm()
    showCreateModal.value = true
}

const openEdit = (row: any) => {
    form.reset()
    form.clearErrors()
    isEditMode.value = true
    editingOvertimeTypeId.value = row.id ?? null

    form.code = row.code ?? ''
    form.name = row.name ?? ''
    form.description = row.description ?? null
    form.category = row.category ?? ''
    form.compensation_type = row.compensation_type ?? ''
    form.multiplier = row.multiplier ?? null
    form.is_active = Boolean(row.is_active)

    showCreateModal.value = true
}

const closeModal = () => {
    showCreateModal.value = false
    resetForm()
}

const submit = () => {
    if (isEditMode.value && editingOvertimeTypeId.value) {
        form.patch(
            route('grp.org.hr.overtime_types.update', {
                ...route().params,
                overtimeType: editingOvertimeTypeId.value,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    showCreateModal.value = false
                    resetForm()
                },
            }
        )
    } else {
        form.post(route('grp.org.hr.overtime_types.store', route().params), {
            preserveScroll: true,
            onSuccess: () => {
                resetForm()
                showCreateModal.value = false
            },
        })
    }
}

const modalTitle = computed(() =>
    isEditMode.value ? trans('Edit overtime type') : trans('Create overtime type')
)
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #button-overtime-type="{ action }">
            <Button
                :icon="action.icon"
                :label="action.label"
                :style="action.style"
                @click="openModal"
            />
        </template>
    </PageHeading>

    <Table :resource="data" class="mt-5">
        <template #cell(action)="{ item }">
            <div class="flex justify-end gap-2">
                <Button
                    type="secondary"
                    label="Edit"
                    icon="fal fa-pencil"
                    size="xs"
                    v-tooltip="trans('Edit overtime type')"
                    @click="openEdit(item)"
                />
                <ModalConfirmationDelete
                    :routeDelete="{
                        name: 'grp.org.hr.overtime_types.delete',
                        parameters: {
                            ...route().params,
                            overtimeType: item.id,
                        },
                    }"
                    :isFullLoading="false"
                    :title="trans('Are you sure you want to delete this overtime type?')"
                    :noLabel="trans('Delete')"
                    noIcon="fal fa-trash"
                >
                    <template #default="{ changeModel }">
                        <Button
                            type="negative"
                            label="Delete"
                            :icon="faTrash"
                            size="xs"
                            v-tooltip="trans('Delete overtime type')"
                            @click="changeModel()"
                        />
                    </template>
                </ModalConfirmationDelete>
            </div>
        </template>
    </Table>

    <Modal :isOpen="showCreateModal" @onClose="closeModal" width="w-full max-w-2xl">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            {{ modalTitle }}
        </h2>

        <form class="space-y-4" @submit.prevent="submit">
            <!-- Code + Name -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('Code') }}
                    </label>
                    <input
                        v-model="form.code"
                        type="text"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <div v-if="form.errors.code" class="mt-1 text-sm text-red-600">
                        {{ form.errors.code }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('Name') }}
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.name }}
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Description') }}
                </label>
                <textarea
                    v-model="form.description"
                    rows="3"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                    {{ form.errors.description }}
                </div>
            </div>

            <!-- Category + Compensation type -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('Category') }}
                    </label>
                    <select
                        v-model="form.category"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">
                            {{ trans('Select category') }}
                        </option>
                        <option
                            v-for="option in props.categoryOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <div v-if="form.errors.category" class="mt-1 text-sm text-red-600">
                        {{ form.errors.category }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('Compensation type') }}
                    </label>
                    <select
                        v-model="form.compensation_type"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">
                            {{ trans('Select compensation type') }}
                        </option>
                        <option
                            v-for="option in props.compensationTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <div v-if="form.errors.compensation_type" class="mt-1 text-sm text-red-600">
                        {{ form.errors.compensation_type }}
                    </div>
                </div>
            </div>

            <!-- Multiplier + Active -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans('Multiplier') }}
                    </label>
                    <input
                        v-model.number="form.multiplier"
                        type="number"
                        step="0.01"
                        min="0"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <div v-if="form.errors.multiplier" class="mt-1 text-sm text-red-600">
                        {{ form.errors.multiplier }}
                    </div>
                </div>

                <div class="flex items-center mt-6">
                    <input
                        id="is_active"
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label for="is_active" class="ml-2 block text-sm text-gray-700">
                        {{ trans('Active') }}
                    </label>
                </div>
            </div>

            <!-- Buttons -->
            <div class="mt-6 flex justify-end gap-2">
                <Button type="tertiary" @click="closeModal">
                    {{ trans('Cancel') }}
                </Button>
                <Button type="save" :loading="form.processing" @click="submit">
                    {{ trans('Save') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
