<script setup lang="ts">
import { computed, inject, onMounted, ref, watch } from 'vue'
import { cloneDeep, isString, isEqual } from 'lodash-es'
import ToggleSwitch from 'primevue/toggleswitch'
import ScreenView from '@/Components/ScreenView.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faInfoCircle } from '@fal'
import type { Ref } from 'vue'
import type { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'
import Editor from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EditorContent } from '@tiptap/vue-3'

const props = withDefaults(
  defineProps<{
    rows?: number
    useIn?: Array<'desktop' | 'tablet' | 'mobile'>
    uploadRoutes: routeType
    class?: string
  }>(),
  {
    rows: 3,
    useIn: ['desktop', 'tablet', 'mobile'],
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void
}>()

const model = defineModel<any>()

const currentView = inject<Ref<'desktop' | 'tablet' | 'mobile'>>(
  'currentView',
  ref('desktop')
)

const sideKey = inject('sideKey', ref(1))

const editorRef = ref<any>(null)
const renderKey = ref(0)

const normalized = ref({
  use_responsive: false,
  desktop: '',
  tablet: null as string | null,
  mobile: null as string | null,
})

let syncingFromParent = false

/* -----------------------------
   Sync FROM parent (NO emit)
-------------------------------- */
function syncFromModel() {
  syncingFromParent = true

  const value = model.value
  let next

  if (isString(value)) {
    next = {
      use_responsive: false,
      desktop: value,
      tablet: null,
      mobile: null,
    }
  } else {
    const v = cloneDeep(value || {})
    next = {
      use_responsive: !!v.use_responsive,
      desktop: v.desktop ?? '',
      tablet: v.tablet ?? null,
      mobile: v.mobile ?? null,
    }
  }

  if (!isEqual(next, normalized.value)) {
    normalized.value = next
  }

  queueMicrotask(() => {
    syncingFromParent = false
  })
}

/* -----------------------------
   Sync TO parent (USER ONLY)
-------------------------------- */
watch(
  normalized,
  (val) => {
    if (syncingFromParent) return
    if (isEqual(val, model.value)) return

    emit('update:modelValue', cloneDeep(val))
  },
  { deep: true }
)

/* -----------------------------
   Lifecycle
-------------------------------- */
onMounted(syncFromModel)
watch(model, syncFromModel, { deep: true })

/* -----------------------------
   Reactive behavior
-------------------------------- */
watch(
  () => normalized.value.use_responsive,
  (enabled) => {
    if (!enabled) {
      normalized.value.tablet = null
      normalized.value.mobile = null
    }
    renderKey.value++
  }
)

watch(
  () => currentView.value,
  () => {
    renderKey.value++
  }
)

/* -----------------------------
   Active text binding
-------------------------------- */
const activeText = computed<string>({
  get() {
    const n = normalized.value
    if (!n.use_responsive) return n.desktop

    const val = n[currentView.value]
    return val == null ? n.desktop : val
  },
  set(val) {
    const n = cloneDeep(normalized.value)

    if (!n.use_responsive) {
      n.desktop = val
    } else {
      n[currentView.value] = val
    }

    normalized.value = n
  },
})

/* -----------------------------
   Force editor sync (NO save)
-------------------------------- */
watch(
  activeText,
  (val) => {
    const editor = editorRef.value?.editor
    if (!editor) return

    const current = editor.getHTML()
    if (current !== val) {
      editor.commands.setContent(val || '')
    }
  },
  { immediate: true }
)
</script>

<template>
  <div class="flex flex-col gap-3 bg-gray-100 p-2 rounded" :key="`${sideKey}-${key}`">
    <div class="flex items-center justify-between">
      <label class="font-medium text-xs">{{ trans('Responsive Text') }}</label>
      <ToggleSwitch v-model="normalized.use_responsive" />
    </div>

    <div class="flex items-center justify-between">
      <div
        class="flex items-center text-gray-500 text-xs"
        v-tooltip="normalized.use_responsive
          ? 'Each screen has its own text. Changing the text on one view will not affect the others.'
          : 'Responsive mode is disabled. The desktop text will be used for all screens (tablet & mobile).'">
        <FontAwesomeIcon :icon="faInfoCircle" class="mr-1" />
        <span>{{ trans('Info') }}</span>
      </div>

      <ScreenView
        v-if="normalized.use_responsive"
        :show-list="props.useIn"
        v-model="currentView"
      />
    </div>

    <div :class="props.class">
      <Editor ref="editorRef" v-model="activeText" :uploadImageRoute="props.uploadRoutes">
        <template #editor-content="{ editor }">
          <div
            class="editor-wrapper border-2 border-gray-300 rounded-lg p-3 shadow-sm transition-all duration-200 focus-within:border-blue-400"
            :style="{ minHeight: `${props.rows * 24}px` }">
            <EditorContent :editor="editor" class="editor-content" />
          </div>
        </template>
      </Editor>
    </div>
  </div>
</template>

<style scoped lang="scss">
.editor-wrapper {
  display: flex;
  flex-direction: column;
  width: 100%;
  overflow-y: auto;
  box-sizing: border-box;
  max-height: 400px;
}

.editor-content {
  flex: 1;
  width: 100%;
  display: block;
}

:deep(.ProseMirror) {
  outline: none;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: break-word;
  width: 100%;
  height: auto;
  min-height: 100%;
  box-sizing: border-box;
  line-height: 1.5;
  font-size: 1rem;
}
</style>
