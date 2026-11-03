<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import MultiSelect from "primevue/multiselect"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMapMarkerAlt, faTrashAlt } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"

library.add(faMapMarkerAlt, faTrashAlt)

type RouteData = { name: string, parameters: object }
type Area = {
    id: number
    name: string
    number_artisans: number
    artefact_department_ids: number[]
    update_route: RouteData
    delete_route: RouteData
}

const props = defineProps<{
    data: {
        can_edit: boolean
        store_route: RouteData
        areas: Area[]
        departments: { id: number, name: string, work_area_id: number | null }[]
    }
}>()

const newName = ref("")
const errors = ref<Record<string, string>>({})
const confirmingDelete = ref<number | null>(null)
const pickedDepartments = ref<Record<number, number[]>>({})
watch(() => props.data.areas, areas => {
    pickedDepartments.value = Object.fromEntries(areas.map(area => [area.id, [...area.artefact_department_ids]]))
}, { immediate: true })

const areaName = (areaId: number | null) => props.data.areas.find(area => area.id === areaId)?.name
const departmentOptions = (area: Area) => props.data.departments.map(department => ({
    id: department.id,
    label: department.work_area_id && department.work_area_id !== area.id
        ? `${department.name} (${areaName(department.work_area_id)})`
        : department.name,
}))
const departmentNames = (area: Area) => props.data.departments.filter(department => area.artefact_department_ids.includes(department.id)).map(department => department.name).join(", ")

function send(key: string, method: "post" | "patch" | "delete", routeData: RouteData, payload: object = {}, onSuccess?: () => void) {
    delete errors.value[key]
    router[method](route(routeData.name, routeData.parameters), payload as never, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => onSuccess?.(),
        onError: (failures: Record<string, string>) => errors.value[key] = Object.values(failures)[0],
    })
}

function addArea() {
    if (!newName.value.trim()) return
    send("new", "post", props.data.store_route, { name: newName.value.trim() }, () => newName.value = "")
}

function rename(area: Area, name: string) {
    if (!name.trim() || name.trim() === area.name) return
    send(`area-${area.id}`, "patch", area.update_route, { name: name.trim() })
}

function saveDepartments(area: Area) {
    const departmentIds = pickedDepartments.value[area.id] ?? []
    if ([...departmentIds].sort().join(",") === [...area.artefact_department_ids].sort().join(",")) return
    send(`area-${area.id}`, "patch", area.update_route, { artefact_department_ids: departmentIds })
}
</script>

<template>
    <div>
        <h2 class="text-lg font-semibold mb-1">
            <FontAwesomeIcon :icon="['fal', 'map-marker-alt']" fixed-width class="text-gray-400 mr-1" aria-hidden="true" />
            {{ ctrans('Work areas') }}
        </h2>
        <p class="text-xs text-gray-500 mb-3">{{ ctrans('Where artisans work. Link each area to the product departments made there: supervisors then see that area\'s artisans first when assigning those products.') }}</p>

        <div v-if="!data.areas.length" class="text-gray-400 text-sm py-4 text-center border border-dashed border-gray-200 rounded-lg mb-2">
            {{ ctrans('No work areas yet') }}
        </div>

        <div v-for="area in data.areas" :key="area.id" class="mb-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm">
            <div class="flex items-center gap-3">
                <InputText v-if="data.can_edit" :key="area.name" :defaultValue="area.name" size="small" class="flex-1 font-medium" :aria-label="ctrans('Area name')"
                    @blur="(event: FocusEvent) => rename(area, (event.target as HTMLInputElement).value)"
                    @keydown.enter="(event: KeyboardEvent) => (event.target as HTMLInputElement).blur()" />
                <span v-else class="flex-1 font-medium">{{ area.name }}</span>
                <span class="shrink-0 text-xs text-gray-500 tabular-nums">{{ area.number_artisans }} {{ area.number_artisans === 1 ? ctrans('artisan') : ctrans('artisans') }}</span>
                <template v-if="data.can_edit">
                    <template v-if="confirmingDelete === area.id">
                        <Button type="negative" size="xs" :label="ctrans('Delete')" @click="send(`area-${area.id}`, 'delete', area.delete_route, {}, () => confirmingDelete = null)" />
                        <Button type="tertiary" size="xs" :label="ctrans('Keep')" @click="confirmingDelete = null" />
                    </template>
                    <button v-else type="button" class="rounded px-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"
                        v-tooltip="ctrans('Delete area')" :aria-label="ctrans('Delete area')" @click="confirmingDelete = area.id">
                        <FontAwesomeIcon :icon="['fal', 'trash-alt']" fixed-width aria-hidden="true" />
                    </button>
                </template>
            </div>
            <p v-if="confirmingDelete === area.id" class="mt-1 text-xs text-gray-500">{{ ctrans('Its artisans and departments are left without an area.') }}</p>
            <div class="mt-2">
                <MultiSelect v-if="data.can_edit" v-model="pickedDepartments[area.id]" :options="departmentOptions(area)" optionLabel="label" optionValue="id"
                    display="chip" filter :placeholder="ctrans('Product departments made here')" :pt="{ root: { class: 'w-full text-xs' }, label: { class: 'text-xs py-1' } }"
                    @hide="saveDepartments(area)" />
                <span v-else class="text-xs text-gray-500">{{ departmentNames(area) || ctrans('No departments linked') }}</span>
            </div>
            <p v-if="errors[`area-${area.id}`]" class="mt-1 text-xs text-red-600">{{ errors[`area-${area.id}`] }}</p>
        </div>

        <div v-if="data.can_edit" class="mt-3 flex items-center gap-2">
            <InputText v-model="newName" size="small" class="flex-1" :placeholder="ctrans('New area, e.g. Oils Area')" :aria-label="ctrans('New area')" @keydown.enter="addArea" />
            <Button type="tertiary" size="s" :label="ctrans('Add area')" :disabled="!newName.trim()" @click="addArea" />
        </div>
        <p v-if="errors.new" class="mt-1 text-xs text-red-600">{{ errors.new }}</p>
    </div>
</template>
