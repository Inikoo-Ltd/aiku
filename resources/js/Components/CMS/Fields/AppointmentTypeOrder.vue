<script setup lang="ts">
import { computed } from "vue"
import draggable from "vuedraggable"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBars } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { orderAppointmentTypes } from "@/Components/CMS/Webpage/Appointment/orderAppointmentTypes"

library.add(faBars)

type AppointmentTypeRow = {
    id: number
    name: string
    meeting_mode_label?: string
    duration_minutes?: number
}

const props = withDefaults(defineProps<{
    modelValue?: number[] | null
    appointmentTypes?: AppointmentTypeRow[]
}>(), {
    modelValue: null,
    appointmentTypes: () => [],
})

const emits = defineEmits<{
    (e: "update:modelValue", value: number[]): void
}>()

const orderedTypes = computed(() => orderAppointmentTypes(props.appointmentTypes, props.modelValue))

const onReorder = (appointmentTypes: AppointmentTypeRow[]) => {
    emits("update:modelValue", appointmentTypes.map(appointmentType => appointmentType.id))
}
</script>

<template>
    <div>
        <draggable
            v-if="orderedTypes.length"
            :modelValue="orderedTypes"
            item-key="id"
            handle=".handle"
            class="flex flex-col gap-1.5"
            @update:modelValue="onReorder">
            <template #item="{ element, index }">
                <div class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs">
                    <FontAwesomeIcon icon="fal fa-bars" class="handle cursor-grab text-gray-400" fixed-width />
                    <span class="w-4 text-gray-400">{{ index + 1 }}</span>
                    <div class="min-w-0">
                        <div class="truncate font-medium text-gray-700">{{ element.name }}</div>
                        <div v-if="element.meeting_mode_label" class="text-gray-400">
                            {{ element.meeting_mode_label }}<template v-if="element.duration_minutes"> · {{ ctrans(":minutes min", { minutes: element.duration_minutes }) }}</template>
                        </div>
                    </div>
                </div>
            </template>
        </draggable>
        <p v-else class="text-xs text-gray-400">
            {{ ctrans("This shop has no active appointment types yet.") }}
        </p>
    </div>
</template>
