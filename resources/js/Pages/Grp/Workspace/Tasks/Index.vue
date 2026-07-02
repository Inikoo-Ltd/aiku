<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { ref } from 'vue';
import TaskModal from '@/Components/Workspace/TaskModal.vue';

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  tasks: Array<any>
}>();

const isModalOpen = ref(false);
const editingTask = ref(null);

const openModal = (task = null) => {
  editingTask.value = task;
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  editingTask.value = null;
};

const updateStatus = (task, status) => {
  router.put(route('grp.workspace.tasks.update', task.id), {
    status: status
  }, {
    preserveScroll: true
  });
};

const deleteTask = (task) => {
  if(confirm('Are you sure you want to delete this task?')) {
    router.delete(route('grp.workspace.tasks.destroy', task.id), {
      preserveScroll: true
    });
  }
};
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #actions>
      <button @click="openModal()" class="btn btn-primary bg-blue-600 text-white px-4 py-2 rounded">
        Add New Task
      </button>
    </template>
  </PageHeading>

  <div class="mt-8">
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
      <ul role="list" class="divide-y divide-gray-200">
        <li v-for="task in tasks" :key="task.id">
          <div class="px-4 py-4 flex items-center sm:px-6">
            <div class="min-w-0 flex-1 sm:flex sm:items-center sm:justify-between">
              <div class="truncate">
                <div class="flex text-sm">
                  <p class="font-medium text-blue-600 truncate">{{ task.title }}</p>
                  <p class="ml-1 flex-shrink-0 font-normal text-gray-500">
                    in {{ task.status }}
                  </p>
                </div>
                <div class="mt-2 flex">
                  <div class="flex items-center text-sm text-gray-500">
                    <p>{{ task.description }}</p>
                  </div>
                </div>
              </div>
              <div class="mt-4 flex-shrink-0 sm:mt-0 sm:ml-5 flex space-x-2">
                  <button @click="updateStatus(task, 'Working on')" class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Working on</button>
                  <button @click="updateStatus(task, 'Ready')" class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Ready</button>
                  <button @click="updateStatus(task, 'Can\'t be done')" class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Can't be done</button>
                  
                  <button @click="openModal(task)" class="text-xs text-blue-600 border border-blue-600 px-2 py-1 rounded ml-4">Edit</button>
                  <button @click="deleteTask(task)" class="text-xs text-red-600 border border-red-600 px-2 py-1 rounded">Delete</button>
              </div>
            </div>
          </div>
        </li>
        <li v-if="tasks.length === 0" class="px-4 py-4 text-center text-gray-500">
          No tasks found.
        </li>
      </ul>
    </div>
  </div>

  <TaskModal :show="isModalOpen" :task="editingTask" @close="closeModal" />
</template>
