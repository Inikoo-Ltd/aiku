<script setup lang="ts">
import { computed } from 'vue'
import PureRadio from '@/Components/Pure/PureRadio.vue'
import { BannerWorkshop } from '@/types/BannerWorkshop'
import { useSolidColor } from '@/Composables/useStockList'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faCheck)

const props = defineProps<{
  modelValue: BannerWorkshop
  fieldName: string
  fieldData?: {
    options: {
      label: string
      name: string
    }[]
  }
}>()

const emit = defineEmits(['update:modelValue'])

const data = computed({
  get() {
    return props.modelValue || {}
  },
  set(v) {
    console.log('set nav data', v)
    emit('update:modelValue', v)
  }
})


const setColor = (color: string) => {
  data.value = {
    ...data.value,
    colorNav: color
  }
}

const setNavValue = (name: string, val: boolean) => {
  data.value = {
    ...data.value,
    [name]: {
      ...(data.value?.[name] || {}),
      value: val
    }
  }
}

const setNavType = (name: string, val: string) => {
  data.value = {
    ...data.value,
    [name]: {
      ...(data.value?.[name] || {}),
      type: val
    }
  }
}


const bottomNavOptions = [
  { value: 'bullets' },
  { value: 'buttons' }
]
</script>

<template>
  <div class="mt-2">
    <!-- colors -->
    <div class="ml-3 w-fit bg-gray-100 border border-gray-300 rounded px-2 py-2 space-y-2 mb-2">
      <div class="leading-none text-xs text-gray-500">
        Colors
      </div>

      <div class="flex gap-x-1">
        <div
          v-for="color in useSolidColor"
          :key="color"
          @click="setColor(color)"
          :style="{ backgroundColor: color }"
          class="relative h-5 aspect-square rounded overflow-hidden shadow cursor-pointer transition-all duration-200"
          :class="{ 'scale-110': data.colorNav === color }"
        >
          <transition name="slide-bot-to-top">
            <div
              v-if="color === data.colorNav"
              class="absolute flex items-center justify-center bg-black/20 inset-0"
            >
              <FontAwesomeIcon fixed-width icon="fal fa-check" class="text-white" />
            </div>
          </transition>
        </div>
      </div>
    </div>

    <!-- options -->
    <table>
      <tbody class="divide-y divide-gray-200">
        <tr v-for="(option, index) in fieldData?.options" :key="index">
          <td class="whitespace-nowrap px-3 text-sm text-gray-500 text-center flex py-1.5">
            <input
              :checked="data?.[option.name]?.value"
              @change="setNavValue(option.name, ($event.target as HTMLInputElement).checked)"
              :id="`item-${index}`"
              type="checkbox"
              class="h-6 w-6 rounded cursor-pointer border-gray-300 hover:border-gray-500 text-gray-600 focus:ring-gray-600"
            />
          </td>

          <td>
            <label
              :for="`item-${index}`"
              class="whitespace-nowrap block py-2 pr-3 text-sm font-medium text-gray-500 hover:text-gray-600 cursor-pointer"
            >
              {{ option.label }}
            </label>

            <PureRadio
              v-if="data?.[option.name]?.value && option.name === 'bottomNav'"
              :modelValue="data[option.name].type"
              @update:modelValue="val => setNavType(option.name, val)"
              :key="`bottomNav-${index}`"
              :name="`bottomNav-${index}`"
              :id="`bottomNav-${index}`"
              :indexChecked="bottomNavOptions.findIndex(i => i.value === data[option.name].type)"
              :options="bottomNavOptions"
            />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
