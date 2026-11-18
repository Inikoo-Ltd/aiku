<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faTrashAlt, faArrowUp, faArrowDown, faCheck, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faPlus, faTrashAlt, faArrowUp, faArrowDown, faCheck, faTimes)

type DropPoint = { id: number, name: string, position: number }

const props = defineProps<{
    productionId: number
    dropPoints: DropPoint[]
}>()

const visible = defineModel<boolean>("visible", { default: false })

const names = ref<Record<number, string>>({})
const newName = ref("")
const errors = ref<Record<string, string>>({})
const busy = ref(false)

watch(() => props.dropPoints, dropPoints => {
    names.value = Object.fromEntries(dropPoints.map(dropPoint => [dropPoint.id, dropPoint.name]))
}, { immediate: true })

const options = (errorKey: string) => ({
    preserveScroll: true,
    onStart: () => {
        busy.value = true
        errors.value = {}
    },
    onError: (formErrors: Record<string, string>) => errors.value = { [errorKey]: Object.values(formErrors)[0] },
    onFinish: () => busy.value = false,
})

const pointRoute = (name: string, dropPoint: DropPoint) => route(name, { production: props.productionId, productionDropPoint: dropPoint.id })

function add() {
    if (!newName.value.trim()) return
    router.post(route("grp.models.production.drop_points.store", { production: props.productionId }), { name: newName.value.trim() }, {
        ...options("new"),
        onSuccess: () => newName.value = "",
    })
}

function rename(dropPoint: DropPoint) {
    const name = names.value[dropPoint.id]?.trim()
    if (!name || name === dropPoint.name) return
    router.patch(pointRoute("grp.models.production.drop_points.update", dropPoint), { name }, options(String(dropPoint.id)))
}

function move(index: number, step: number) {
    const reordered = props.dropPoints.map(dropPoint => dropPoint.id)
    const [moved] = reordered.splice(index, 1)
    reordered.splice(index + step, 0, moved)
    router.patch(route("grp.models.production.drop_points.reorder", { production: props.productionId }), { drop_point_ids: reordered }, options("order"))
}

function remove(dropPoint: DropPoint) {
    router.delete(pointRoute("grp.models.production.drop_points.delete", dropPoint), options(String(dropPoint.id)))
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('Drop points')" :style="{ width: '30rem' }">
        <p class="mb-3 text-xs text-gray-500">{{ ctrans("Places in the factory where the warehouse leaves what is asked for.") }}</p>

        <div class="divide-y divide-gray-100 text-sm">
            <div v-for="(dropPoint, index) in dropPoints" :key="dropPoint.id" class="py-1.5">
                <div class="flex items-center gap-1.5">
                    <InputText v-model="names[dropPoint.id]" class="flex-1 !py-1 !text-sm" maxlength="64" :aria-label="ctrans('Name')" @change="rename(dropPoint)" />
                    <Button type="tertiary" size="xs" :disabled="busy || index === 0" v-tooltip="ctrans('Move up')" @click="move(index, -1)">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-arrow-up" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" size="xs" :disabled="busy || index === dropPoints.length - 1" v-tooltip="ctrans('Move down')" @click="move(index, 1)">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-arrow-down" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" size="xs" :disabled="busy" v-tooltip="ctrans('Delete :name', { name: dropPoint.name })" @click="remove(dropPoint)">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <div v-if="errors[dropPoint.id]" class="mt-1 text-xs text-red-600">{{ errors[dropPoint.id] }}</div>
            </div>
        </div>
        <div v-if="errors.order" class="mt-1 text-xs text-red-600">{{ errors.order }}</div>

        <div class="mt-3">
            <div class="flex items-center gap-2">
                <InputText v-model="newName" class="flex-1 !py-1 !text-sm" maxlength="64" :placeholder="ctrans('New drop point')" :aria-label="ctrans('New drop point')" @keyup.enter="add" />
                <Button type="tertiary" size="s" :label="ctrans('Add')" :disabled="busy || !newName.trim()" @click="add">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
            <div v-if="errors.new" class="mt-1 text-xs text-red-600">{{ errors.new }}</div>
        </div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="primary" full :label="ctrans('Done')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
