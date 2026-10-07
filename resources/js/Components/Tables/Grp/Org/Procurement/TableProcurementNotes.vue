<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Popover from 'primevue/popover'
import { notify } from '@kyvg/vue3-notification'
import Table from '@/Components/Table/Table.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'
import { faStickyNote } from '@fal'
import { routeType } from '@/types/route'

const props = defineProps<{
    data: object
    tab?: string
    storeRoute?: routeType
}>()

const popover = ref()
const note = ref('')
const isSaving = ref(false)

function addNote() {
    if (!props.storeRoute || !note.value.trim()) {
        return
    }

    router.post(route(props.storeRoute.name, props.storeRoute.parameters), { note: note.value }, {
        preserveScroll: true,
        onStart: () => { isSaving.value = true },
        onFinish: () => { isSaving.value = false },
        onSuccess: () => {
            note.value = ''
            popover.value?.hide()
            router.reload({ only: [props.tab ?? 'notes'] })
        },
        onError: (errors) => {
            notify({
                title: ctrans('Something went wrong'),
                text: Object.values(errors)[0] ?? ctrans('Failed to add the note'),
                type: 'error',
            })
        },
    })
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template v-if="storeRoute" #add-on-button>
            <Button :label="ctrans('Add note')" type="create" size="xs" :icon="faStickyNote" @click="(event) => popover.toggle(event)" />
            <Popover ref="popover">
                <div class="flex flex-col gap-3 w-[24rem]">
                    <PureTextarea v-model="note" :rows="5" :placeholder="ctrans('Write a note')" />
                    <div class="flex justify-end">
                        <Button type="save" size="xs" :loading="isSaving" :disabled="!note.trim() || isSaving" @click="addNote" />
                    </div>
                </div>
            </Popover>
        </template>

        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.created_at, { formatTime: 'hm' }) }}</span>
        </template>

        <template #cell(author)="{ item }">
            {{ item.author ?? '-' }}
        </template>

        <template #cell(note)="{ item }">
            <div class="whitespace-pre-line" :class="{ 'line-through text-gray-400': item.strikethrough }">{{ item.note }}</div>
            <div v-if="item.purchase_order_reference" class="text-xs text-gray-500">
                {{ ctrans('On purchase order') }} {{ item.purchase_order_reference }}
            </div>
        </template>
    </Table>
</template>
