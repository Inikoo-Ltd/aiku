<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import { useFormatTime } from "@/Composables/useFormatTime"

const props = defineProps<{
    isOpen: boolean
}>()

const emit = defineEmits<{
    close: []
    created: []
}>()

type PreviewRow = { line: number; subject: string; who: string; due_at: string | null; error: string | null }

const list = ref("")
const rows = ref<PreviewRow[] | null>(null)
const errors = ref<string[]>([])
const busy = ref(false)

const hasErrors = computed(() => rows.value?.some((row) => row.error) ?? false)

watch(() => props.isOpen, (open) => {
    if (!open) return
    list.value = ""
    rows.value = null
    errors.value = []
})

watch(list, () => { rows.value = null })

const check = async () => {
    busy.value = true
    errors.value = []
    try {
        const { data } = await axios.post(route("grp.tasks.import"), { list: list.value, preview: true })
        rows.value = data.data
    } catch (error: any) {
        errors.value = Object.values(error.response?.data?.errors ?? {}).flat() as string[]
        if (!errors.value.length) errors.value = [ctrans("Something went wrong")]
    } finally {
        busy.value = false
    }
}

const raise = async () => {
    busy.value = true
    errors.value = []
    try {
        const { data } = await axios.post(route("grp.tasks.import"), { list: list.value })
        emit("close")
        emit("created")
        notify({ title: ctrans(":count tasks raised", { count: data.data.length }), type: "success" })
    } catch (error: any) {
        errors.value = Object.values(error.response?.data?.errors ?? {}).flat() as string[]
        if (!errors.value.length) errors.value = [ctrans("Something went wrong")]
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-[99] flex items-center justify-center bg-black/40 p-4" @click.self="emit('close')" @keydown.esc="emit('close')">
            <div class="w-full max-w-3xl max-h-[90vh] overflow-y-auto bg-white rounded-2xl p-6 shadow-xl text-left space-y-4">
                <h3 class="text-base font-semibold text-gray-900">{{ ctrans('Raise tasks from a list') }}</h3>

                <div class="text-xs text-gray-500 space-y-1">
                    <p>{{ ctrans('One task per line. Paste cells from Excel, or separate with |') }}</p>
                    <p class="font-mono text-gray-700">{{ ctrans('task') }} | {{ ctrans('person or department') }} | {{ ctrans('due date') }}</p>
                    <p>{{ ctrans('Only the task is needed. With no person or department the task is yours.') }}</p>
                </div>

                <textarea
                    v-model="list"
                    rows="8"
                    autofocus
                    :placeholder="ctrans('Update the Christmas banner | Marketing | 30/10/2026')"
                    class="w-full px-3 py-2 text-sm font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]" />

                <ul v-if="errors.length" class="text-xs text-red-600 space-y-0.5">
                    <li v-for="error in errors" :key="error">{{ error }}</li>
                </ul>

                <table v-if="rows?.length" class="w-full text-sm border border-gray-200 rounded-md">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-2 py-1.5 text-left font-normal">#</th>
                            <th class="px-2 py-1.5 text-left font-normal">{{ ctrans('Task') }}</th>
                            <th class="px-2 py-1.5 text-left font-normal">{{ ctrans('For') }}</th>
                            <th class="px-2 py-1.5 text-left font-normal">{{ ctrans('Due') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in rows" :key="row.line" :class="row.error ? 'bg-red-50' : ''">
                            <td class="px-2 py-1.5 text-xs text-gray-400 align-top">{{ row.line }}</td>
                            <td class="px-2 py-1.5 align-top">
                                {{ row.subject }}
                                <div v-if="row.error" class="text-xs text-red-600">{{ row.error }}</div>
                            </td>
                            <td class="px-2 py-1.5 align-top whitespace-nowrap">{{ row.who }}</td>
                            <td class="px-2 py-1.5 align-top whitespace-nowrap">{{ row.due_at ? useFormatTime(row.due_at) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else-if="rows" class="text-xs text-gray-500">{{ ctrans('The list is empty') }}</p>

                <div class="flex justify-end gap-x-2 pt-2">
                    <button type="button" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900" @click="emit('close')">{{ ctrans('Cancel') }}</button>
                    <button v-if="!rows" type="button" :disabled="busy || !list.trim()" class="px-4 py-2 text-sm rounded-md bg-[--app-accent] text-[--app-accent-text] hover:bg-[--app-accent-strong] disabled:opacity-40" @click="check">{{ ctrans('Check list') }}</button>
                    <button v-else type="button" :disabled="busy || hasErrors || !rows.length" class="px-4 py-2 text-sm rounded-md bg-[--app-accent] text-[--app-accent-text] hover:bg-[--app-accent-strong] disabled:opacity-40" @click="raise">{{ ctrans('Raise :count tasks', { count: rows.length }) }}</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
