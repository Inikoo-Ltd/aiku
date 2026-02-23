<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import Table from '@/Components/Table/Table.vue'
import Modal from '@/Components/Utils/Modal.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { ref, watch, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core";
import { faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faDownload, faFileExcel, faFileCsv, faUmbrella } from "@fal";

library.add(faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faDownload, faFileExcel, faFileCsv, faUmbrella)


const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: any
    typeOptions: { value: string; label: string }[]
}>()

const showCreateModal = ref(false)
const showEditModal = ref(false)
const editingHoliday = ref<any | null>(null)
const toTouchedCreate = ref(false)
const toTouchedEdit = ref(false)
const filterYear = ref<string>('')
const filterMonth = ref<string>('')

const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear()

    return Array.from({ length: 5 }, (_, index) => {
        const year = currentYear - 2 + index

        return {
            value: String(year),
            label: String(year),
        }
    })
})

const form = useForm<{
    type: string
    label: string
    from: string
    to: string
    is_recurring: boolean
}>({
    type: '',
    label: '',
    from: '',
    to: '',
    is_recurring: false,
})

const editForm = useForm<{
    type: string
    label: string
    from: string
    to: string
    is_recurring: boolean
}>({
    type: '',
    label: '',
    from: '',
    to: '',
    is_recurring: false,
})

const initializeFiltersFromUrl = () => {
    const url = new URL(window.location.href)

    const yearParam = url.searchParams.get('filter[year]')
    const monthParam = url.searchParams.get('filter[month]')

    filterYear.value = yearParam ?? ''
    filterMonth.value = monthParam ?? ''
}

initializeFiltersFromUrl()

const applyFilters = () => {
    const params: Record<string, unknown> = {
        ...route().params,
    }

    const filter: Record<string, string> = {}

    if (filterYear.value) {
        filter.year = filterYear.value
    }

    if (filterMonth.value) {
        filter.month = filterMonth.value
    }

    if (Object.keys(filter).length > 0) {
        params.filter = filter
    }

    router.get(route('grp.org.hr.holidays.index', params), {}, { preserveState: true, preserveScroll: true })
}

const resetFilters = () => {
    filterYear.value = ''
    filterMonth.value = ''

    const params: Record<string, unknown> = {
        ...route().params,
    }

    params.filter = undefined

    router.get(route('grp.org.hr.holidays.index', params), {}, { preserveState: true, preserveScroll: true })
}

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
    form.post(route('grp.org.hr.holidays.store', route().params), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            closeCreateModal()
        },
    })
}

const openEditModal = (holiday: any) => {
    editingHoliday.value = holiday
    editForm.clearErrors()
    toTouchedEdit.value = false
    editForm.type = holiday.type
    editForm.label = holiday.label ?? ''
    editForm.from = holiday.from
    editForm.to = holiday.to
    editForm.is_recurring = holiday.is_recurring ?? false
    showEditModal.value = true
}

const closeEditModal = () => {
    showEditModal.value = false
    editForm.clearErrors()
}

const submitEdit = () => {
    if (!editingHoliday.value) {
        return
    }

    editForm.patch(
        route('grp.org.hr.holidays.update', {
            ...route().params,
            holiday: editingHoliday.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                editForm.reset()
                editingHoliday.value = null
                closeEditModal()
            },
        }
    )
}

watch(
    () => form.from,
    (newFrom) => {
        if (newFrom && (!form.to || !toTouchedCreate.value)) {
            form.to = newFrom
        }
    }
)

watch(
    () => editForm.from,
    (newFrom) => {
        if (newFrom && (!editForm.to || !toTouchedEdit.value)) {
            editForm.to = newFrom
        }
    }
)
</script>

<template layout="Grp">
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-create-holiday="{ action }">
            <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <div>
                        <select
                            v-model="filterYear"
                            class="mt-0.5 block w-28 rounded-md border-gray-300 px-2 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">
                                {{ trans('All years') }}
                            </option>
                            <option
                                v-for="year in yearOptions"
                                :key="year.value"
                                :value="year.value"
                            >
                                {{ year.label }}
                            </option>
                        </select>
                    </div>
                    <div>

                        <select
                            v-model="filterMonth"
                            class="mt-0.5 block w-32 rounded-md border-gray-300 px-2 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">
                                {{ trans('All months') }}
                            </option>
                            <option value="1">{{ trans('January') }}</option>
                            <option value="2">{{ trans('February') }}</option>
                            <option value="3">{{ trans('March') }}</option>
                            <option value="4">{{ trans('April') }}</option>
                            <option value="5">{{ trans('May') }}</option>
                            <option value="6">{{ trans('June') }}</option>
                            <option value="7">{{ trans('July') }}</option>
                            <option value="8">{{ trans('August') }}</option>
                            <option value="9">{{ trans('September') }}</option>
                            <option value="10">{{ trans('October') }}</option>
                            <option value="11">{{ trans('November') }}</option>
                            <option value="12">{{ trans('December') }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            type="secondary"
                            size="xs"
                            :label="trans('Filter')"
                            @click="applyFilters"
                        />
                        <Button
                            type="tertiary"
                            size="xs"
                            :label="trans('Reset')"
                            @click="resetFilters"
                        />
                    </div>
                </div>
                <Button
                    type="create"
                    size="xs"
                    :icon="action.icon"
                    :label="action.label"
                    @click="openCreateModal"
                />
            </div>
        </template>
    </PageHeading>

    <Modal :isOpen="showCreateModal" @onClose="closeCreateModal" width="w-full max-w-lg">
        <form class="space-y-4" @submit.prevent="submitCreate">
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Type') }}
                </label>
                <select
                    v-model="form.type"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option value="">
                        {{ trans('Select type') }}
                    </option>
                    <option
                        v-for="option in typeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <div v-if="form.errors.type" class="mt-1 text-xs text-red-600">
                    {{ form.errors.type }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Name') }}
                </label>
                <input
                    v-model="form.label"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div v-if="form.errors.label" class="mt-1 text-xs text-red-600">
                    {{ form.errors.label }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('From') }}
                </label>
                <input
                    v-model="form.from"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div v-if="form.errors.from" class="mt-1 text-xs text-red-600">
                    {{ form.errors.from }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('To') }}
                </label>
                <input
                    v-model="form.to"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    @change="toTouchedCreate = true"
                />
                <div v-if="form.errors.to" class="mt-1 text-xs text-red-600">
                    {{ form.errors.to }}
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="is_recurring"
                    v-model="form.is_recurring"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <label for="is_recurring" class="text-sm text-gray-700">
                    {{ trans('Repeats every year (fixed holiday)') }}
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
                    {{ trans('Type') }}
                </label>
                <select
                    v-model="editForm.type"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option value="">
                        {{ trans('Select type') }}
                    </option>
                    <option
                        v-for="option in typeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <div v-if="editForm.errors.type" class="mt-1 text-xs text-red-600">
                    {{ editForm.errors.type }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('Name') }}
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
                    {{ trans('From') }}
                </label>
                <input
                    v-model="editForm.from"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
                <div v-if="editForm.errors.from" class="mt-1 text-xs text-red-600">
                    {{ editForm.errors.from }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ trans('To') }}
                </label>
                <input
                    v-model="editForm.to"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    @change="toTouchedEdit = true"
                />
                <div v-if="editForm.errors.to" class="mt-1 text-xs text-red-600">
                    {{ editForm.errors.to }}
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="edit_is_recurring"
                    v-model="editForm.is_recurring"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <label for="edit_is_recurring" class="text-sm text-gray-700">
                    {{ trans('Repeats every year (fixed holiday)') }}
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
        <template #cell(year)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.year }}
            </span>
        </template>

        <template #cell(label)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.label || '—' }}
            </span>
        </template>

        <template #cell(type_label)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.type_label }}
            </span>
        </template>

        <template #cell(is_recurring)="{ item }">
            <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="item.is_recurring ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'"
            >
                {{ item.is_recurring ? trans('Fixed') : trans('One-off') }}
            </span>
        </template>

        <template #cell(from)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.from }}
            </span>
        </template>

        <template #cell(to)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.to }}
            </span>
        </template>

        <template #cell(duration_days)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.duration_days }}
            </span>
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
                <ModalConfirmationDelete
                    :routeDelete="{
                        name: 'grp.org.hr.holidays.delete',
                        parameters: {
                            ...route().params,
                            holiday: item.id,
                        },
                    }"
                    :title="trans('Are you sure you want to delete this holiday?')"
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
            </div>
        </template>
    </Table>
</template>
