<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { onMounted, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTasks, faPlus, faComments } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import StaffTaskDialog from "@/Components/Tasks/StaffTaskDialog.vue"
import { useStaffMessaging } from "@/Stores/staff-messaging"

const props = defineProps<{
    modelType: "Product" | "Customer" | "Order" | "DeliveryNote"
    modelId: number
}>()

const store = useStaffMessaging()
const tasks = ref<any[]>([])
const dialogOpen = ref(false)

const load = async () => {
    const { data } = await axios.get(route("grp.tasks.list"), { params: { view: "model", model_type: props.modelType, model_id: props.modelId } })
    tasks.value = data.data
}

const openThread = (task: any) => store.openTaskThread(task)

onMounted(load)
</script>

<template>
    <div class="flex items-center gap-x-2 flex-wrap text-xs">
        <button 
            v-tooltip="trans('Ask a colleague or a department to do something about this')" 
            class="leading-4 inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed xmin-w-max bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70 rounded-md px-3 md:px-4 py-[6px] md:py-[9px] text-sm" 
            @click="dialogOpen = true"
        >
            <FontAwesomeIcon icon="fal fa-tasks" fixed-width aria-hidden="true" />
            {{ trans('Task') }}
        </button>
        <button
            v-for="task in tasks"
            :key="task.id"
            v-tooltip="task.status_label + ' · ' + (task.assignee?.name ?? task.department_label ?? '')"
            class="flex items-center gap-x-1 px-2 py-1 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 max-w-[16rem]"
            @click="openThread(task)">
            <FontAwesomeIcon :icon="task.status_icon.icon" :class="task.status_icon.class" fixed-width aria-hidden="true" />
            <span class="truncate">{{ task.subject }}</span>
        </button>

        <StaffTaskDialog :is-open="dialogOpen" :model-type="modelType" :model-id="modelId" @close="dialogOpen = false" @created="load" />
    </div>
</template>
