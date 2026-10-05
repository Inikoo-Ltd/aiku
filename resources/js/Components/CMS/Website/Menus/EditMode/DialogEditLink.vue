<script setup lang="ts">
import { ref } from 'vue'
import cloneDeep from 'lodash-es/cloneDeep'
import { ctrans } from '@/Composables/useTrans'
import PureInput from '@/Components/Pure/PureInput.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Link from '@/Components/CMS/Fields/Link.vue'

const props = defineProps<{
  modelValue: {
    label?: string
    link?: Record<string, any>
  }
}>()

const emit = defineEmits<{
  (e: 'onSave', value: { label?: string, link?: Record<string, any> }): void
  (e: 'onCancel'): void
}>()

const form = ref(cloneDeep({ label: props.modelValue?.label ?? '', link: props.modelValue?.link ?? {} }))
</script>

<template>
  <form class="space-y-3" @submit.prevent="emit('onSave', form)">
    <div>
      <label class="mb-1 block text-xs font-medium text-gray-600">{{ ctrans('Label') }}</label>
      <PureInput v-model="form.label" />
    </div>

    <div>
      <label class="mb-1 block text-xs font-medium text-gray-600">{{ ctrans('Link') }}</label>
      <Link v-model="form.link" />
    </div>

    <div class="flex justify-end gap-2 border-t border-gray-200 pt-3">
      <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="emit('onCancel')" />
      <Button type="save" size="xs" :label="ctrans('Save')" @click="emit('onSave', form)" />
    </div>
  </form>
</template>
