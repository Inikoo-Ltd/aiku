<script setup lang="ts">
import type { FieldValue } from '@/types/Website/Website/footer1'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import SideEditorInputHTML from '@/Components/CMS/Fields/SideEditorInputHTML.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { computed, onMounted } from 'vue'

library.add(faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faWhatsapp)

const props = defineProps<{ modelValue: FieldValue; translation: string }>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: FieldValue): void
  (e: 'save'): void
  (e: 'cancel'): void
}>()

const value = computed({
  get: () => props.modelValue.data.fieldValue,
  set: val => emit('update:modelValue', { ...props.modelValue, data: { ...props.modelValue.data, fieldValue: val } })
})

const visibleColumns = computed(() => Object.values(value.value.columns).slice(0, 3))

onMounted(() => {
  visibleColumns.value.forEach(column =>
    Object.values(column.data).slice(0, 3).forEach(menu => {
      menu.translate ||= {}
      menu.translate[props.translation] ??= menu.name || ''
      Object.values(menu.data).slice(0, 3).forEach(item => {
        item.translate ||= {}
        item.translate[props.translation] ??= item.name || ''
      })
    })
  )
})

const handle = (type: 'save' | 'cancel') => emit(type)
</script>

<template>
  <div class="relative pb-20">
    <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="(column, cIdx) in visibleColumns" :key="column.id || cIdx"
        class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-all duration-200">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Column {{ cIdx + 1 }}</h3>
        <div v-for="(menu, mIdx) in column.data" :key="menu.id || mIdx" class="mb-6 pb-4 border-b border-gray-100 last:border-none">
          <div class="mb-3">
            <div class="text-xs uppercase text-gray-400 mb-1">Master</div>
            <div class="font-medium text-gray-700 bg-gray-50 rounded-md p-2" v-html="menu.name"></div>
          </div>
          <SideEditorInputHTML v-model="menu.translate[translation]" :rows="1" />
          <ul class="space-y-4 mt-4">
            <li v-for="(item, iIdx) in menu.data" :key="item.id || iIdx" class="bg-gray-50 p-3 rounded-lg">
              <div class="text-xs uppercase text-gray-400 mb-1">Master</div>
              <div class="font-medium text-gray-700 mb-2" v-html="item.name"></div>
              <SideEditorInputHTML v-model="item.translate[translation]" :rows="1" />
            </li>
          </ul>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-all duration-200">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Column 4</h3>
        
      </div>
    </div>

    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg p-4 flex justify-end gap-3 z-50">
      <Button @click="handle('cancel')" type="gray" label="Cancel" />
      <Button @click="handle('save')" type="save" />
    </div>
  </div>
</template>
