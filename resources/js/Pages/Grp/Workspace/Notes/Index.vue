<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { ref } from 'vue';
import NoteModal from '@/Components/Workspace/NoteModal.vue';

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  notes: Array<any>
}>();

const isModalOpen = ref(false);
const editingNote = ref(null);

const openModal = (note = null) => {
  editingNote.value = note;
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  editingNote.value = null;
};

const deleteNote = (note) => {
  if(confirm('Are you sure you want to delete this note?')) {
    router.delete(route('grp.workspace.notes.destroy', note.id), {
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
        Add New Note
      </button>
    </template>
  </PageHeading>

  <div class="mt-8">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="note in notes" :key="note.id" class="bg-yellow-50 overflow-hidden shadow rounded-lg flex flex-col">
        <div class="px-4 py-5 sm:p-6 flex-1 border-b border-yellow-200">
          <h3 class="text-lg leading-6 font-medium text-gray-900">{{ note.title }}</h3>
          <div class="mt-2 max-w-xl text-sm text-gray-500 whitespace-pre-wrap">
            <p>{{ note.content }}</p>
          </div>
        </div>
        <div class="bg-yellow-100 px-4 py-3 sm:px-6 flex justify-end space-x-2">
            <button @click="openModal(note)" class="text-xs text-blue-600 border border-blue-600 px-2 py-1 rounded bg-white">Edit</button>
            <button @click="deleteNote(note)" class="text-xs text-red-600 border border-red-600 px-2 py-1 rounded bg-white">Delete</button>
        </div>
      </div>
    </div>
    
    <div v-if="notes.length === 0" class="text-center text-gray-500 mt-8">
      No notes found.
    </div>
  </div>

  <NoteModal :show="isModalOpen" :note="editingNote" @close="closeModal" />
</template>
