<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import Dialog from 'primevue/dialog'
import PureInput from '@/Components/Pure/PureInput.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { ctrans } from '@/Composables/useTrans'

const props = defineProps<{
    shopId: number
}>()

const ROW_TYPES = ['header', 'footer', 'block']

const saveModalOpen = ref(false)
const deleteModalOpen = ref(false)
const isSaving = ref(false)
const rowName = ref('')
const rowType = ref('footer')
const editingId = ref<string | null>(null)
const pendingRow = ref<any>(null)
const pendingResolve = ref<((value: any) => void) | null>(null)
const pendingReject = ref<(() => void) | null>(null)

const closeAndReject = () => {
    saveModalOpen.value = false
    deleteModalOpen.value = false
    pendingReject.value?.()
    pendingResolve.value = null
    pendingReject.value = null
    pendingRow.value = null
    editingId.value = null
}

const openModal = (row: any) => {
    return new Promise((resolve, reject) => {
        pendingRow.value = row
        pendingResolve.value = resolve
        pendingReject.value = reject
        editingId.value = null
        rowName.value = row?.metadata?.name ?? ''
        rowType.value = row?.metadata?.rowType ?? 'footer'
        saveModalOpen.value = true
    })
}

const openEditModal = (args: any) => {
    return new Promise((resolve, reject) => {
        pendingRow.value = args?.row
        pendingResolve.value = resolve
        pendingReject.value = reject
        editingId.value = args?.row?.metadata?.idRow ?? null
        rowName.value = args?.row?.metadata?.name ?? ''
        rowType.value = args?.row?.metadata?.rowType ?? 'footer'
        saveModalOpen.value = true
    })
}

const openDeleteModal = (args: any) => {
    return new Promise((resolve, reject) => {
        pendingRow.value = args?.row
        pendingResolve.value = resolve
        pendingReject.value = reject
        editingId.value = args?.row?.metadata?.idRow ?? null
        rowName.value = args?.row?.metadata?.name ?? ''
        deleteModalOpen.value = true
    })
}

const saveRow = async () => {
    if (!rowName.value) {
        return
    }

    isSaving.value = true

    try {
        if (editingId.value) {
            await axios.patch(
                route('grp.models.email-templates.rows.update', { emailTemplate: editingId.value }),
                { name: rowName.value, layout: pendingRow.value, row_type: rowType.value }
            )
        } else {
            await axios.post(
                route('grp.models.shop.email-template-row.store', { shop: props.shopId }),
                { name: rowName.value, layout: pendingRow.value, row_type: rowType.value }
            )
        }

        notify({
            title: ctrans('Success'),
            text: ctrans('Saved successfully'),
            type: 'success',
        })

        saveModalOpen.value = false
        pendingResolve.value?.(editingId.value ? true : { ...pendingRow.value, metadata: { name: rowName.value, rowType: rowType.value } })
        pendingResolve.value = null
        pendingReject.value = null
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error.response?.data?.message || ctrans('Failed to save'),
            type: 'error',
        })
    } finally {
        isSaving.value = false
    }
}

const deleteRow = async () => {
    if (!editingId.value) {
        closeAndReject()
        return
    }

    isSaving.value = true

    try {
        await axios.delete(route('grp.models.email-templates.rows.delete', { emailTemplate: editingId.value }))

        notify({
            title: ctrans('Success'),
            text: ctrans('Deleted successfully'),
            type: 'success',
        })

        deleteModalOpen.value = false
        pendingResolve.value?.(true)
        pendingResolve.value = null
        pendingReject.value = null
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error.response?.data?.message || ctrans('Failed to delete'),
            type: 'error',
        })
    } finally {
        isSaving.value = false
    }
}

defineExpose({
    openModal,
    openEditModal,
    openDeleteModal,
})
</script>

<template>
    <Dialog v-model:visible="saveModalOpen" modal :closable="false" :showHeader="false" :style="{ width: '25rem' }">
        <div class="pt-4">
            <div class="font-semibold mb-3">{{ ctrans('Name') }}</div>
            <PureInput v-model="rowName" :placeholder="ctrans('e.g. Ancient Wisdom footer')" :disabled="isSaving" />

            <div class="font-semibold mb-3 mt-4">{{ ctrans('Type') }}</div>
            <div class="flex gap-2">
                <button v-for="type in ROW_TYPES" :key="type" type="button" @click="rowType = type" :disabled="isSaving"
                    class="px-3 py-1.5 text-sm rounded border capitalize"
                    :class="rowType === type ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300'">
                    {{ ctrans(type) }}
                </button>
            </div>

            <div class="flex justify-end mt-4 gap-3">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="closeAndReject" :disabled="isSaving" />
                <Button type="save" @click="saveRow" :loading="isSaving" :disabled="isSaving || !rowName" />
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="deleteModalOpen" modal :closable="false" :showHeader="false" :style="{ width: '25rem' }">
        <div class="pt-4">
            <div class="font-semibold mb-3">{{ ctrans('Delete :name?', { name: rowName }) }}</div>
            <div class="text-sm text-gray-500">
                {{ ctrans('It will no longer appear in the saved rows library. Emails already using it are not changed.') }}
            </div>
            <div class="flex justify-end mt-4 gap-3">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="closeAndReject" :disabled="isSaving" />
                <Button type="delete" @click="deleteRow" :loading="isSaving" :disabled="isSaving" />
            </div>
        </div>
    </Dialog>
</template>
