<script setup lang='ts'>
import { useChatThemes, applyChatTheme } from '@/Composables/useChatThemes'

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
    }
}>()

const onClickTheme = (key: string) => {
    props.form[props.fieldName] = key
    applyChatTheme(key)
}
</script>

<template>
    <div class="relative w-full">
        <div class="flex flex-wrap gap-x-2 gap-y-3">
            <div
                v-for="(theme, key) in useChatThemes"
                :key="key"
                @click="() => onClickTheme(key as string)"
                class="relative h-20 aspect-[16/9] w-fit flex ring-1 ring-gray-300 hover:ring-2 hover:ring-gray-500 shadow rounded overflow-hidden cursor-pointer bg-white"
            >
                <div class="w-1/3 h-full bg-white border-r border-gray-200 flex flex-col gap-1 justify-center px-1.5">
                    <div class="h-0.5 w-6 rounded bg-gray-300" />
                    <div class="h-0.5 w-5 rounded bg-gray-200" />
                    <div class="h-0.5 w-4 rounded bg-gray-200" />
                </div>
                <div class="flex-1 h-full flex flex-col justify-center gap-1 px-2" :style="{backgroundColor: theme.bg}">
                    <div class="h-0.5 w-8 rounded" :style="{backgroundColor: theme.text}" />
                    <div class="h-0.5 w-6 rounded" :style="{backgroundColor: theme.muted}" />
                    <div class="h-0.5 w-4 rounded" :style="{backgroundColor: theme.accent}" />
                    <span class="text-[10px]" :style="{color: theme.label}">{{ theme.name }}</span>
                </div>

                <Transition name="slide-to-right">
                    <div v-if="form[fieldName] === key" class="absolute inset-0 bg-gray-600/30 flex items-center justify-center text-white text-xs">
                        Selected
                    </div>
                </Transition>
            </div>
        </div>
    </div>
</template>
