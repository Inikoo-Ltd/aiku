<script setup lang="ts">
import type { FieldValue } from '@/types/Website/Website/footer1'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import SideEditorInputHTML from '@/Components/CMS/Fields/SideEditorInputHTML.vue'
import { computed, onMounted } from 'vue'

library.add(faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faWhatsapp)

const props = defineProps<{
  modelValue: FieldValue
  translation: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: FieldValue): void
}>()

// Reactive binding to fieldValue
const value = computed({
  get: () => props.modelValue.data.fieldValue,
  set: (val) => {
    emit('update:modelValue', {
      ...props.modelValue,
      data: {
        ...props.modelValue.data,
        fieldValue: val
      }
    })
  }
})

// Only take first 3 columns
const visibleColumns = computed(() => Object.values(value.value.columns).slice(0, 3))

onMounted(() => {
  for (const column of visibleColumns.value) {
    const menuKeys = Object.keys(column.data).slice(0, 3)
    for (const mKey of menuKeys) {
      const menu = column.data[mKey]

      if (!menu.translate) menu.translate = {}
      if (menu.translate[props.translation] == undefined) {
        menu.translate[props.translation] = menu.name || ''
      }

      const itemKeys = Object.keys(menu.data).slice(0, 3)
      for (const iKey of itemKeys) {
        const menu_item = menu.data[iKey]
        if (!menu_item.translate) menu_item.translate = {}
        if (menu_item.translate[props.translation] == undefined) {
          menu_item.translate[props.translation] = menu_item.name || ''
        }
      }
    }
  }
})
</script>

<template>
  <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
    <div
      v-for="(column, cIdx) in visibleColumns"
      :key="column.id || cIdx"
      class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 hover:shadow-xl transition-all duration-200"
    >
      <h3 class="text-lg font-semibold text-gray-800 mb-4">
        Column {{ cIdx + 1 }}
      </h3>

      <div
        v-for="(menu, mIdx) in column.data"
        :key="menu.id || mIdx"
        class="mb-6 pb-4 border-b border-gray-100 last:border-none last:pb-0"
      >
        <!-- Menu Name -->
        <div class="mb-3">
          <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
            Master
          </div>
          <div class="font-medium text-gray-700 bg-gray-50 rounded-md p-2" v-html="menu.name"></div>
        </div>

        <!-- Menu Translation -->
        <SideEditorInputHTML
          v-model="menu.translate[translation]"
          :rows="1"
        />

        <!-- Menu Items -->
        <ul class="space-y-4 mt-4">
          <li
            v-for="(menu_item, iIdx) in menu.data"
            :key="menu_item.id || iIdx"
            class="bg-gray-50 p-3 rounded-lg"
          >
            <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
              Master
            </div>
            <div class="font-medium text-gray-700 mb-2" v-html="menu_item.name"></div>

            <SideEditorInputHTML
              v-model="menu_item.translate[translation]"
              :rows="1"
            />
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
