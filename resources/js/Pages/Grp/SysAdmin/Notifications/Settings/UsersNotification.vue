<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Table from '@/Components/Table/Table.vue'
import { capitalize } from '@/Composables/capitalize'
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Multiselect from '@vueform/multiselect'
import axios from 'axios'
import { ref, watch } from 'vue'

const props = defineProps<{
  pageHead: any
  title: string
  settings: any
}>()

const showModal = ref(false)

const usersOptions = ref<{value:number,label:string}[]>([])
const typesOptions = ref<{value:number,label:string,category:string}[]>([])
const groupsOptions = ref<{value:number,label:string}[]>([])
const organisationsOptions = ref<{value:number,label:string}[]>([])
const shopsOptions = ref<{value:number,label:string}[]>([])
const stateOptions = ref<{value:string,label:string}[]>([])

const form = ref({
  user_id: null as number|null,
  notification_type_id: null as number|null,
  scope_kind: null as 'group'|'organisation'|'shop'|null,
  scopes: [] as Array<number>,
  filters: { state: [] as string[] },
})

const loadOptions = async () => {
  const { data } = await axios.get(route('grp.sysadmin.notification-settings.users.options'))
  usersOptions.value = data.users
  typesOptions.value = data.notification_types
  groupsOptions.value = data.groups
  organisationsOptions.value = data.organisations
  shopsOptions.value = data.shops
}

const loadStateOptions = async () => {
  if (!form.value.notification_type_id) {
    stateOptions.value = []
    return
  }
  const type = typesOptions.value.find(t => t.value === form.value.notification_type_id)
  if (!type) {
    stateOptions.value = []
    return
  }
  const { data } = await axios.get(route('grp.sysadmin.notification-settings.state-options'), {
    params: { notification_type_id: form.value.notification_type_id }
  })
  if (data.states && data.states.length > 0) {
    stateOptions.value = [{ value: 'all', label: 'All' }, ...data.states]
  } else {
    stateOptions.value = []
  }
}

watch(() => form.value.filters.state, (newVal, oldVal) => {
  if (newVal.includes('all') && !oldVal.includes('all')) {
    form.value.filters.state = stateOptions.value
      .filter(opt => opt.value !== 'all')
      .map(opt => opt.value)
  }
})

watch(() => form.value.notification_type_id, () => {
  loadStateOptions()
})

const scopeList = () => {
  if (form.value.scope_kind === 'group') return groupsOptions.value
  if (form.value.scope_kind === 'organisation') return organisationsOptions.value
  if (form.value.scope_kind === 'shop') return shopsOptions.value
  return []
}

const resetForm = () => {
  form.value = {
    user_id: null,
    notification_type_id: null,
    scope_kind: null,
    scopes: [],
    filters: { state: [] }
  }
}

const openModal = async () => {
  await loadOptions()
  resetForm()
  showModal.value = true
}

const submit = async () => {
  const payload = {
    user_id: form.value.user_id,
    notification_type_id: form.value.notification_type_id,
    scope_kind: form.value.scope_kind,
    scopes: form.value.scopes,
    filters: form.value.filters
  }
  await axios.post(route('grp.sysadmin.notification-settings.users.store'), payload)
  showModal.value = false
  await router.reload({ only: ['settings'] })
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

    <Table :resource="settings" :name="'userNotificationSettings'">
      <template #cell(types)="{ item }">
        <div class="flex flex-col gap-1">
          <div
            v-for="(setting, idx) in item.user_settings"
            :key="idx"
            class="py-1 text-sm text-gray-700"
            :class="{ 'border-b border-gray-200': idx !== item.user_settings.length - 1 }"
          >
            {{ setting.type }}
          </div>
          <span v-if="!item.user_settings.length" class="text-gray-400">-</span>
        </div>
      </template>

      <template #cell(scopes)="{ item }">
        <div class="flex flex-col gap-1">
          <div
            v-for="(setting, idx) in item.user_settings"
            :key="idx"
            class="py-1 text-sm text-indigo-700"
            :class="{ 'border-b border-gray-200': idx !== item.user_settings.length - 1 }"
          >
            <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs">
              {{ setting.scope }}
            </span>
          </div>
          <span v-if="!item.user_settings.length" class="text-gray-400">-</span>
        </div>
      </template>

      <template #cell(filters)="{ item }">
        <div class="flex flex-col gap-1">
          <div
            v-for="(setting, idx) in item.user_settings"
            :key="idx"
            class="flex flex-wrap gap-1 py-1"
            :class="{ 'border-b border-gray-200': idx !== item.user_settings.length - 1 }"
          >
            <template v-if="setting.filters && Object.keys(setting.filters).length">
              <template v-for="(val, key) in setting.filters" :key="key">
                <template v-if="key === 'state' && Array.isArray(val)">
                  <span
                    v-for="state in val"
                    :key="state"
                    class="inline-flex items-center rounded-md bg-amber-100 px-2 py-1 text-xs text-amber-900"
                  >
                    {{ state }}
                  </span>
                </template>
                <template v-else>
                   <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-800">
                    {{ key }}: {{ Array.isArray(val) ? val.join(', ') : val }}
                  </span>
                </template>
              </template>
            </template>
            <span v-else class="text-gray-400 text-xs py-1">-</span>
          </div>
          <span v-if="!item.user_settings.length" class="text-gray-400">-</span>
        </div>
      </template>
    </Table>

    <Modal :isOpen="showModal" @onClose="showModal = false" title="Assign Notification" width="w-full max-w-lg">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">User</label>
          <Multiselect
            v-model="form.user_id"
            :options="usersOptions"
            :searchable="true"
            placeholder="Select user"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Notification Type</label>
          <Multiselect
            v-model="form.notification_type_id"
            :options="typesOptions"
            :searchable="true"
            placeholder="Select type"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Scope Kind</label>
          <Multiselect
            v-model="form.scope_kind"
            :options="[
              { value: 'group', label: 'Group' },
              { value: 'organisation', label: 'Organisation' },
              { value: 'shop', label: 'Shop' }
            ]"
            placeholder="Select scope kind"
          />
        </div>

        <div v-if="form.scope_kind">
          <label class="block text-sm font-medium text-gray-700">Scopes</label>
          <Multiselect
            v-model="form.scopes"
            :options="scopeList()"
            mode="tags"
            :searchable="true"
            placeholder="Select scopes"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">States Filter</label>
          <Multiselect
            v-model="form.filters.state"
            :options="stateOptions"
            mode="tags"
            :searchable="true"
            placeholder="Select states (optional)"
          />
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
