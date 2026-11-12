<!--
  - Author: Rifqi Taufiqurrohman
  - Created: Thu, 12 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dialog from "primevue/dialog"
import { Link, router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBroadcastTower, faEye, faHistory, faTimes, faUndo } from "@fal"

library.add(faBroadcastTower, faEye, faHistory, faTimes, faUndo)

defineProps<{
    data: object
    tab?: string
    canRestore?: boolean
}>()

interface SnapshotRow {
    created_at: string
    part: string
    restore_route: { name: string, parameters: (string | number)[] } | null
}

const restoring = ref<SnapshotRow | null>(null)
const isVisible = ref(false)
const isSaving = ref(false)
const error = ref<string | null>(null)

const askRestore = (row: SnapshotRow) => {
    restoring.value = row
    error.value = null
    isVisible.value = true
}

const restore = () => {
    if (!restoring.value?.restore_route) {
        return
    }
    isSaving.value = true
    router.post(route(restoring.value.restore_route.name, restoring.value.restore_route.parameters), {}, {
        preserveScroll: true,
        onSuccess: () => {
            isVisible.value = false
        },
        onError: (errors) => {
            error.value = Object.values(errors)[0] as string ?? ctrans("The version could not be restored")
        },
        onFinish: () => {
            isSaving.value = false
        },
    })
}
</script>

<template>
    <div>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(part)="{ item }">
            <Link :href="route(item.part_route.name, item.part_route.parameters)" class="whitespace-nowrap font-mono text-sm text-gray-700 hover:text-[var(--app-accent)] hover:underline">
                {{ item.part }}
            </Link>
        </template>
        <template #cell(actions)="{ item }">
            <div class="flex justify-end gap-2">
                <a :href="item.preview_url" target="_blank" rel="noopener">
                    <Button type="tertiary" size="xs" icon="fal fa-eye" :label="ctrans('Preview')" />
                </a>
                <Button v-if="canRestore && item.restore_route" type="tertiary" size="xs" :label="ctrans('Restore')" @click="askRestore(item)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faUndo" class="text-amber-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Table>

    <Dialog v-model:visible="isVisible" modal :header="ctrans('Restore this version?')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-3 text-sm text-gray-700">
            <p>
                {{ ctrans(":part goes back to how it was on :date.", { part: restoring?.part ?? '', date: useFormatTime(restoring?.created_at, { formatTime: 'aiku' }) }) }}
            </p>
            <p class="text-gray-500">
                {{ ctrans("The current version is kept as a snapshot, so this can be undone the same way.") }}
            </p>
            <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
        </div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isVisible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Restore')" :loading="isSaving" @click="restore">
                    <template #icon>
                        <FontAwesomeIcon :icon="faUndo" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
    </div>
</template>
