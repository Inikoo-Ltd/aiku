<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref } from "vue"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Tag from "@/Components/Tag.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import TaskModal from "@/Components/Workspace/TaskModal.vue"

const props = defineProps<{
  pageHead : PageHeadingTypes
  title : string
  data: any
  employees: { value: number; label: string }[]
  statuses: Record<string, string>
  canEdit: boolean
  employeeId: number | null
}>();

const statusTheme: Record<string, number> = {
  pending: 99,
  working_on: 1,
  ready: 3,
  cant_be_done: 7,
}

const isModalOpen = ref(false)
const editingTask = ref<any>(null)

const openModal = (task: any = null) => {
  editingTask.value = task
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  editingTask.value = null
}
 
const updateStatus = (task: any, status: string) => {
  router.put(
    route("grp.workspace.tasks.update", task.id),
    { status },
    { preserveScroll: true }
  )
}


// const deleteTask = (task : any) => {
//   if(confirm('Are you sure you want to delete this task?')) {
//     router.delete(route('grp.workspace.tasks.destroy', task.id), {
//       preserveScroll: true
//     });
//   }
// };
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #button-task="{ action }">
      <Button :icon="action.icon" :label="action.label" :style="action.style" @click="openModal()" />
    </template>
  </PageHeading>

   <Table :resource="data" class="mt-5">
    <template #cell(title)="{ item }">
      <span class="font-medium text-gray-900">{{ item.title }}</span>
      <p v-if="item.description" class="text-sm text-gray-500">{{ item.description }}</p>
    </template>

  <template #cell(status)="{ item }">
      <Tag :theme="statusTheme[item.status] ?? 99" :label="statuses[item.status] ?? item.status" />
    </template>
    <template #cell(assignee)="{ item }">
      <span class="text-gray-600">{{ item.assignee?.contact_name || item.assignee?.alias || "-" }}</span>
    </template>
    <template #cell(assigner)="{ item }">
      <span class="text-gray-600">{{ item.assigner?.contact_name || item.assigner?.alias || "-" }}</span>
    </template>
    <template #cell(actions)="{ item }">
      <div class="flex items-center justify-end gap-2">
        <template v-if="!canEdit && item.assignee_id === employeeId">
          <select
            :value="item.status"
            class="rounded-md border border-gray-300 py-1 text-xs focus:border-indigo-500 focus:ring-indigo-500"
            @change="updateStatus(item, ($event.target as HTMLSelectElement).value)">
            <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
          </select>
        </template>
        <template v-if="canEdit">
          <Button type="tertiary" icon="fal fa-pencil" size="xs" v-tooltip="trans('Edit task')" @click="openModal(item)" />
          <ModalConfirmationDelete
            :routeDelete="{ name: 'grp.workspace.tasks.destroy', parameters: { task: item.id } }"
            :title="trans('Are you sure you want to delete this task?')"
            :noLabel="trans('Delete')"
            noIcon="fal fa-trash">
            <template #default="{ changeModel }">
              <Button type="negative" icon="fal fa-trash" size="xs" v-tooltip="trans('Delete task')" @click="changeModel()" />
            </template>
          </ModalConfirmationDelete>
        </template>
      </div>
    </template>
  </Table>
  <TaskModal :show="isModalOpen" :task="editingTask" :employees="employees" :statuses="statuses" @close="closeModal" />
</template>
