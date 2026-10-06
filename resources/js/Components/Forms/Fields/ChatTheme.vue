<script setup lang='ts'>
import { useChatThemes, applyChatTheme } from '@/Composables/useChatThemes'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheck } from '@fas'
import ChatThemeSwatch from '@/Components/Utils/ChatThemeSwatch.vue'

library.add(faCheck)

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
    <div class="grid w-full grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <button v-for="(theme, key) in useChatThemes" :key="key" type="button"
            class="relative rounded-md transition hover:shadow-md focus:outline-none"
            :class="form[fieldName] === key ? 'ring-2 ring-offset-2 ring-[color:var(--app-accent)]' : ''"
            @click="() => onClickTheme(key as string)">
            <ChatThemeSwatch :theme-key="key as string" />
            <span v-if="form[fieldName] === key"
                class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-[color:var(--app-accent)] text-[10px] text-[color:var(--app-accent-text)] shadow">
                <FontAwesomeIcon icon="fas fa-check" fixed-width aria-hidden="true" />
            </span>
        </button>
    </div>
</template>
