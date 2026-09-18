<!--
 Author Louis Perez
 Created on 18-09-2026-13h-11m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"

type Tab = { key: string; label: string; count?: number | null; highlight?: boolean }

const props = defineProps<{
    tabs: Tab[]
    storageKey: string
}>()

const readStoredTab = (): string | null => {
    try {
        return localStorage.getItem(props.storageKey)
    } catch {
        return null
    }
}

const chosenTab = ref<string | null>(readStoredTab())

const activeTab = computed(() => props.tabs.find((tab) => tab.key === chosenTab.value)?.key ?? props.tabs[0]?.key ?? null)

const chooseTab = (key: string) => {
    chosenTab.value = key
    try {
        localStorage.setItem(props.storageKey, key)
    } catch {
        return
    }
}

const countClass = (tab: Tab) => {
    if (tab.highlight) return "bg-[--app-accent] text-[--app-accent-text]"
    return activeTab.value === tab.key ? "bg-[--app-accent-soft] text-[--app-accent-strong]" : "bg-gray-100 text-gray-600"
}
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden">
        <div class="flex gap-1 overflow-x-auto overflow-y-hidden px-2 shadow-[inset_0_-1px_0_theme(colors.gray.200)]">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-semibold transition duration-200"
                :class="activeTab === tab.key ? 'border-[--app-accent] text-[--app-accent-strong]' : 'border-transparent text-gray-500 hover:text-gray-800'"
                @click="chooseTab(tab.key)"
            >
                {{ tab.label }}
                <span v-if="tab.count !== undefined && tab.count !== null && (!tab.highlight || tab.count > 0)" class="rounded-full px-2 py-0.5 text-xs font-normal tabular-nums" :class="countClass(tab)">{{ tab.count }}</span>
            </button>
        </div>
        <div v-for="tab in tabs" v-show="activeTab === tab.key" :key="tab.key">
            <slot :name="tab.key" />
        </div>
    </div>
</template>
