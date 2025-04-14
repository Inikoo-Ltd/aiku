<script setup lang="ts">
import { inject } from 'vue'
import { trans } from 'laravel-vue-i18n'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { faBorderOuter } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faUnlink } from "@fal"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

// Tambahkan ikon ke library FontAwesome
library.add(faBorderOuter, faLink, faUnlink)

// Menggunakan defineModel() dengan nilai default
const model = defineModel<{ value: number; unit: string }>({
  default: () => ({ value: 0, unit: 'px' }) 
})


const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

/* const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') }) */

// Fungsi untuk memperbarui model
const updateModel = (key: keyof typeof model.value, newValue: any) => {
  model.value = { ...model.value, [key]: newValue }
  /* onSaveWorkshopFromId(side_editor_block_id) */
  console.log(model.value)
  emits('update:modelValue', model.value)
}
</script>

<template>
  <div class="flex flex-col pt-1 pb-3">
    <div class="pb-2">
      <div class="px-3 flex justify-between items-center mb-2">
        <div class="text-xs">{{ trans('Type Unit') }}</div>
        <Popover v-slot="{ open }" class="relative">
          <PopoverButton :class="open ? 'text-indigo-500' : ''" class="underline">
            {{ model.unit }}
          </PopoverButton>

          <transition enter-active-class="transition duration-200 ease-out"
                      enter-from-class="translate-y-1 opacity-0"
                      enter-to-class="translate-y-0 opacity-100"
                      leave-active-class="transition duration-150 ease-in"
                      leave-from-class="translate-y-0 opacity-100"
                      leave-to-class="translate-y-1 opacity-0">
            <PopoverPanel v-slot="{ close }"
                          class="bg-white shadow mt-3 absolute top-full right-0 z-10 w-32 transform rounded overflow-hidden">
              <div @click="() => { updateModel('unit', 'px'); close() }" 
                   class="px-4 py-1.5 cursor-pointer"
                   :class="model.unit === 'px' ? 'bg-indigo-500 text-white' : 'hover:bg-indigo-100'">
                px
              </div>
              <div @click="() => { updateModel('unit', '%'); close() }" 
                   class="px-4 py-1.5 cursor-pointer"
                   :class="model.unit === '%' ? 'bg-indigo-500 text-white' : 'hover:bg-indigo-100'">
                %
              </div>
            </PopoverPanel>
          </transition>
        </Popover>
      </div>

      <div class="pl-2 pr-4 flex items-center relative">
        <div class="relative">
          <Transition name="slide-to-up">
            <div>
              <div class="grid grid-cols-5 items-center">
                <FontAwesomeIcon icon="fad fa-border-outer" v-tooltip="trans('Width')" class="" fixed-width aria-hidden="true" />
                <div class="col-span-4">
                  <PureInputNumber
                    :modelValue="model.value"
                    @update:modelValue="(newVal) => updateModel('value', newVal)"
                    class=""
                    :suffix="model.unit"
                  />
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>
  </div>
</template>
