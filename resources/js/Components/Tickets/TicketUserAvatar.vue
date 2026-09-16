<!--
 Author Louis Perez
 Created on 15-09-2026-10h-17m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"

const props = withDefaults(defineProps<{
    name: string | null
    avatar?: Record<string, string> | null
    size?: "xs" | "sm" | "md" | "lg"
}>(), { avatar: null, size: "md" })

const hasLoaded = ref(false)

watch(
    () => props.avatar?.original,
    () => {
        hasLoaded.value = false
    }
)

const initials = computed(() =>
    (props.name ?? "?")
        .split(/[\s-]+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join("") || "?"
)

const sizeClasses: Record<string, string> = {
    xs: "h-5 w-5 text-[8px]",
    sm: "h-6 w-6 text-[9px]",
    md: "h-7 w-7 text-[10px]",
    lg: "h-9 w-9 text-xs",
}

const sizeClass = computed(() => sizeClasses[props.size])
</script>

<template>
    <span class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-200 font-medium text-gray-600" :class="sizeClass">
        <span v-show="!hasLoaded" aria-hidden="true">{{ initials }}</span>
        <img
            v-if="avatar?.original"
            :src="avatar.original"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
            :class="hasLoaded ? 'opacity-100' : 'opacity-0'"
            @load="hasLoaded = true"
            @error="hasLoaded = false" />
    </span>
</template>
