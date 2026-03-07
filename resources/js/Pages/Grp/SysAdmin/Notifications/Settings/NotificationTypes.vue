<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Table from '@/Components/Table/Table.vue'
import { capitalize } from '@/Composables/capitalize'
import Modal from '@/Components/Utils/Modal.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Multiselect from '@vueform/multiselect'
import TextInput from '@/Components/Forms/Fields/Input.vue'
import TextArea from '@/Components/Forms/Fields/Textarea.vue'
import { ref } from 'vue'

const props = defineProps<{
  pageHead: any
  title: string
  types: any
  channelOptions: {value:string,label:string}[]
}>()

const showModal = ref(false)
const editMode = ref(false)
const editingTypeId = ref<number|null>(null)

const form = useForm({
  name: '',
  slug: '',
  category: '',
  description: '',
  available_channels: [] as string[],
  default_channels: [] as string[],
})

const resetForm = () => {
  form.reset()
  form.clearErrors()
  editMode.value = false
  editingTypeId.value = null
}

const openModal = () => {
  resetForm()
  showModal.value = true
}

const openEditModal = (item: any) => {
  editMode.value = true
  editingTypeId.value = item.id
  form.name = item.name
  form.slug = item.slug
  form.category = item.category
  form.description = item.description
  form.available_channels = item.available_channels || []
  form.default_channels = item.default_channels || []
  showModal.value = true
}

const submit = () => {
  if (editMode.value && editingTypeId.value) {
    form.put(route('grp.sysadmin.notification-settings.types.update', editingTypeId.value), {
      onSuccess: () => {
        showModal.value = false
        form.reset()
      },
    })
  } else {
    form.post(route('grp.sysadmin.notification-settings.types.store'), {
      onSuccess: () => {
        showModal.value = false
        form.reset()
      },
    })
  }
}

const handleDelete = async () => {
  await router.reload({ only: ['types'] })
}
</script>

<template>
  <div>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
      <template #button-new="{ action }">
        <Button
          :style="action.style"
          :label="action.label"
          :icon="action.icon"
          @click="openModal"
        />
      </template>
    </PageHeading>

    <Table :resource="types" :name="'notificationTypes'">
      <template #cell(name)="{ item }">
        <span class="text-sm font-medium text-gray-900">{{ item.name }}</span>
      </template>
      <template #cell(slug)="{ item }">
        <span class="text-sm text-gray-500 font-mono bg-gray-50 px-2 py-1 rounded">{{ item.slug }}</span>
      </template>
      <template #cell(category)="{ item }">
        <span class="text-sm text-gray-600">{{ item.category }}</span>
      </template>
      <template #cell(description)="{ item }">
        <span class="text-sm text-gray-500">{{ item.description || '-' }}</span>
      </template>
      <template #cell(available_channels)="{ item }">
        <div class="flex gap-1">
          <span
            v-for="channel in item.available_channels"
            :key="channel"
            class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10"
          >
            {{ channel }}
          </span>
          <span v-if="!item.available_channels || !item.available_channels.length" class="text-gray-400 text-xs">-</span>
        </div>
      </template>
      <template #cell(default_channels)="{ item }">
        <div class="flex gap-1">
          <span
            v-for="channel in item.default_channels"
            :key="channel"
            class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10"
          >
            {{ channel }}
          </span>
          <span v-if="!item.default_channels || !item.default_channels.length" class="text-gray-400 text-xs">-</span>
        </div>
      </template>
      <template #cell(actions)="{ item }">
        <div class="flex items-center gap-2">
          <Button
            type="secondary"
            icon="fal fa-pencil"
            size="xs"
            @click="openEditModal(item)"
          />
          <ModalConfirmationDelete
            :routeDelete="{
              name: 'grp.sysadmin.notification-settings.types.delete',
              parameters: { notificationType: item.id },
            }"
            :title="'Are you sure you want to delete this notification type?'"
            :noLabel="'Delete'"
            noIcon="fal fa-trash"
            @onSuccess="handleDelete"
          >
            <template #default="{ changeModel }">
              <Button
                @click="changeModel"
                type="negative"
                icon="fal fa-trash-alt"
                size="xs"
              />
            </template>
          </ModalConfirmationDelete>
        </div>
      </template>
    </Table>

    <Modal :isOpen="showModal" @onClose="showModal = false" :title="editMode ? 'Edit Notification Type' : 'Create Notification Type'" width="w-full max-w-lg">
      <div class="space-y-4">
        <TextInput
          :form="form"
          fieldName="name"
          :fieldData="{ type: 'text', placeholder: 'Name' }"
        />

        <TextInput
          :form="form"
          fieldName="slug"
          :fieldData="{ type: 'text', placeholder: 'Slug' }"
        />

        <TextInput
          :form="form"
          fieldName="category"
          :fieldData="{ type: 'text', placeholder: 'Category' }"
        />

        <TextArea
          :form="form"
          fieldName="description"
          :fieldData="{ placeholder: 'Description', counter: true }"
        />

        <div>
          <label class="block text-sm font-medium text-gray-700">Available Channels</label>
          <Multiselect
            v-model="form.available_channels"
            :options="channelOptions"
            mode="tags"
            placeholder="Select channels"
          />
          <div v-if="form.errors.available_channels" class="text-xs text-red-600 mt-1">{{ form.errors.available_channels }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Default Channels</label>
          <Multiselect
            v-model="form.default_channels"
            :options="channelOptions"
            mode="tags"
            placeholder="Select channels"
          />
          <div v-if="form.errors.default_channels" class="text-xs text-red-600 mt-1">{{ form.errors.default_channels }}</div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700" @click="showModal=false">
            Cancel
          </button>
          <button class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700" @click="submit">
            Save
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>
