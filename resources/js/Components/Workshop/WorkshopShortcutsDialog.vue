<script setup lang="ts">
import { computed } from "vue"
import Dialog from "primevue/dialog"
import { ctrans } from "@/Composables/useTrans"
import { formatShortcutKey, WorkshopShortcut } from "@/Composables/useWorkshopShortcuts"

const props = defineProps<{
  shortcuts: Pick<WorkshopShortcut, "id" | "group" | "label" | "combos">[]
}>()

const visible = defineModel<boolean>("visible", { default: false })

const shortcutGroups = computed(() => {
  const groups = new Map<string, typeof props.shortcuts>()
  props.shortcuts.forEach(shortcut => {
    groups.set(shortcut.group, [...(groups.get(shortcut.group) ?? []), shortcut])
  })

  return [...groups.entries()].map(([name, shortcuts]) => ({ name, shortcuts }))
})
</script>

<template>
  <Dialog v-model:visible="visible" modal dismissableMask :header="ctrans('Keyboard shortcuts')"
    :style="{ width: '44rem' }" :breakpoints="{ '768px': '95vw' }">
    <div class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
      <section v-for="group in shortcutGroups" :key="group.name">
        <h3 class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
          {{ ctrans(group.name) }}
        </h3>
        <ul class="divide-y divide-slate-100">
          <li v-for="shortcut in group.shortcuts" :key="shortcut.id"
            class="flex items-center justify-between gap-3 py-1.5 text-sm text-slate-700">
            <span>{{ ctrans(shortcut.label) }}</span>
            <span class="flex shrink-0 items-center gap-1.5">
              <template v-for="(combo, comboIndex) in shortcut.combos" :key="comboIndex">
                <span v-if="comboIndex > 0" class="text-[10px] text-slate-400">{{ ctrans('or') }}</span>
                <span class="flex items-center gap-0.5">
                  <kbd v-for="token in combo" :key="token"
                    class="min-w-[1.5rem] rounded border border-slate-200 border-b-2 bg-slate-50 px-1.5 py-0.5 text-center font-sans text-[11px] leading-none text-slate-600">
                    {{ formatShortcutKey(token) }}
                  </kbd>
                </span>
              </template>
            </span>
          </li>
        </ul>
      </section>
    </div>
    <p class="mt-5 text-xs text-slate-400">
      {{ ctrans('Shortcuts pause while you type in a field or text block, so undo, copy and paste work on the text there.') }}
    </p>
  </Dialog>
</template>
