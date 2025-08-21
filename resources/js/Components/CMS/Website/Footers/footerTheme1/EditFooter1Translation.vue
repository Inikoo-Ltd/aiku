<script setup lang="ts">
import type { FieldValue } from '@/types/Website/Website/footer1'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import SideEditorInputHTML from '@/Components/CMS/Fields/SideEditorInputHTML.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { reactive, onMounted, ref } from 'vue'
import { ulid } from 'ulid'

library.add(faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faWhatsapp)

const props = defineProps<{ modelValue: FieldValue; translation: string }>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: FieldValue): void
  (e: 'save'): void
  (e: 'cancel'): void
}>()

// clone props to avoid mutating directly
const localValue = reactive<FieldValue>(JSON.parse(JSON.stringify(props.modelValue)))
const key = ref(ulid())
const visibleColumns = Object.values(localValue.data.fieldValue.columns).slice(0, 3)

// ✅ Normalize column 4 immediately so template never sees undefined
const column4 = localValue.data.fieldValue.columns.column_4
if (column4 && column4.data) {
  Object.entries(column4.data).forEach(([keyName, val]) => {
    if (typeof val === 'string' || val == null) {
      column4.data[keyName] = {
        text: val || '',
        translate: { [props.translation]: val || '' }
      }
    } else {
      val.translate ||= {}
      val.translate[props.translation] ??= val.text || ''
    }
  })
}

onMounted(() => {
  // process columns 1–3
  visibleColumns.forEach(column =>
    Object.values(column.data).slice(0, 3).forEach(menu => {
      menu.translate ||= {}
      menu.translate[props.translation] ??= menu.name || ''
      Object.values(menu.data).slice(0, 3).forEach(item => {
        item.translate ||= {}
        item.translate[props.translation] ??= item.name || ''
      })
    })
  )

  key.value = ulid()
  console.log('Visible columns after initialization:', visibleColumns)
  console.log('Column 4 after initialization:', column4)
})

const handle = (type: 'save' | 'cancel') => {
  if (type === 'save') {
    emit('update:modelValue', JSON.parse(JSON.stringify(localValue)))
  }
  emit(type)
}
</script>

<template>
  <div class="relative pb-20">
    <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
      <!-- Columns 1–3 -->
      <div
        v-for="(column, cIdx) in visibleColumns"
        :key="column.id || cIdx"
        class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-all duration-200"
      >
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Column {{ cIdx + 1 }}</h3>
        <div
          v-for="(menu, mIdx) in column.data"
          :key="menu.id || mIdx"
          class="mb-6 pb-4 border-b border-gray-100 last:border-none"
        >
          <div class="mb-3">
            <div class="text-xs uppercase text-gray-400 mb-1">Master</div>
            <div class="font-medium text-gray-700 bg-gray-50 rounded-md p-2" v-html="menu.name"></div>
          </div>
          <SideEditorInputHTML v-model="menu.translate[translation]" :rows="1" :key="key" />
          <ul class="space-y-4 mt-4">
            <li
              v-for="(item, iIdx) in menu.data"
              :key="item.id || iIdx"
              class="bg-gray-50 p-3 rounded-lg"
            >
              <div class="text-xs uppercase text-gray-400 mb-1">Master</div>
              <div class="font-medium text-gray-700 mb-2" v-html="item.name"></div>
              <SideEditorInputHTML v-model="item.translate[translation]" :rows="1" :key="key" />
            </li>
          </ul>
        </div>
      </div>

      <!-- Column 4 -->
      <div
        class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-all duration-200"
      >
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
          {{ localValue.data.fieldValue.columns.column_4.name }}
        </h3>

        <div
          v-for="(val, keyName) in localValue.data.fieldValue.columns.column_4.data"
          :key="keyName"
          class="mb-6"
        >
          <div class="text-xs uppercase text-gray-400 mb-1 text-gray-700">{{ keyName }}</div>
          <!-- ✅ Optional chaining to avoid undefined -->
          <SideEditorInputHTML
            v-if="val?.translate"
            v-model="val.translate[translation]"
            :key="key"
          />
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div
      class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg p-4 flex justify-end gap-3 z-50"
    >
      <Button @click="handle('cancel')" type="gray" label="Cancel" />
      <Button @click="handle('save')" type="save" />
    </div>
  </div>
</template>

<style scoped lang="scss">
p {
  color: black !important;
}
</style>