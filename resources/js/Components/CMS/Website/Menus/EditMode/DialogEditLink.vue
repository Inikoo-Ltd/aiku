<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import PureInput from '@/Components/Pure/PureInput.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Link from '@/Components/CMS/Fields/Link.vue'

const props = defineProps<{
  modelValue: any
}>()

const emit = defineEmits<{
  (e: 'onSave', value: NavigationModel): void
}>()


</script>

<template>
  <div>
    <div>
      <div class="my-2 text-gray-500 text-xs tracking-wide mb-2">{{ trans('Label') }}</div>
      <PureInput v-model="props.modelValue.label" />
    </div>

    <div class="mt-2">
      <Link v-model="props.modelValue.link" />
    </div>

    <div class="flex justify-end mt-3">
      <Button type="save" @click="emit('onSave', props.modelValue)" />
    </div>
  </div>
</template>
