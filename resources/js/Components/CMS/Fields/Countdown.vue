<script setup lang="ts">
import { onMounted, watch, computed, inject } from 'vue'
import { trans } from 'laravel-vue-i18n'
import PureDatePicker from '@/Components/Pure/PureDatePicker.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { get, set } from 'lodash-es'
import SideEditorInputHTML from './SideEditorInputHTML.vue'
import Editor2 from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EditorContent } from '@tiptap/vue-3'

// Example data
// "countdown": {
//     "date": "2024-12-12T02:35:00.000Z",
//     "expired_text": "<p><span style=\"color: #9e958b\"><em>Promo was ended</em></span></p>"
// }

// const Countdown = {
//     date: new Date(Date.now() + 2 * 24 * 60 * 60 * 1000),
//     expired_text: ''
// }

const props = defineProps<{
    noToday?: boolean
}>()

const model = defineModel()
// const announcementData = inject('announcementData', Countdown)

// onMounted(() => {
//     if (!get(announcementData, 'fields.countdown', false)) {
//         set(announcementData, 'fields.countdown', Countdown)
//     }
// })
</script>

<template>
    <div class="pb-2">
        <div class="px-3 flex flex-col mb-2">
            <div class="text-xs">{{ trans('Select end date') }}</div>
            
            <!-- Date -->
            <div class="flex items-center gap-x-2 py-1 w-full" >
                <PureDatePicker
                    :modelValue="get(model, 'date', new Date())"
                    @update:modelValue="(e) => set(model, 'date', e)"
                    required
                    :noToday
                    :min-date="new Date()"
                />
            </div>
        </div>

        <div class="px-3 flex flex-col mb-2">
            <div class="text-xs">{{ trans('Enter text (when countdown expired)') }}</div>

            <!-- Text -->
            <div class="flex items-center gap-x-2 py-1 w-full" >
                <Editor2
                    :modelValue="get(model, 'expired_text', '')"
                    @update:modelValue="(e) => set(model, 'expired_text', e)"
                    v-bind="$attrs"
                    class="w-full"
                    :placeholder="trans('Enter text')"
                >
                    <template #editor-content="{ editor }">
                        <div class="bg-gray-200 editor-wrapper border-2 border-gray-300 rounded-lg px-3 py-2 shadow-sm focus-within:border-blue-400">
                            <EditorContent :editor="editor" class="focus:outline-none" />
                        </div>
                    </template>
                </Editor2>

                <!-- <SideEditorInputHTML
                    :modelValue="get(model, 'expired_text', '')"
                    @update:modelValue="(e) => (console.log(e), set(model, 'expired_text', e))"
                /> -->
            </div>
        </div>
    </div>
</template>

<style scoped>
.editor-wrapper {
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

:deep(.editor-class) {
  min-height: 150px;
  font-size: 1rem;
  line-height: 1.5;
}
</style>
