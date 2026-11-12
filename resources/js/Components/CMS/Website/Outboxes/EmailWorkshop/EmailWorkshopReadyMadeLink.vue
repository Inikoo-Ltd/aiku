<script setup lang="ts">
import { computed } from 'vue'
import Select from 'primevue/select'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink } from '@fal'
import { ctrans } from '@/Composables/useTrans'
import { type ReadyMadeLink, findReadyMadeLink } from '@/Components/Forms/Fields/BubleTextEditor/linkHref'

export type { ReadyMadeLink }

const props = defineProps<{
    readyMadeLinks: ReadyMadeLink[]
}>()

const href = defineModel<string>({ default: '' })

const selectedLink = computed(() => findReadyMadeLink(props.readyMadeLinks, href.value))

const selectedKey = computed({
    get: () => selectedLink.value?.key ?? null,
    set: (key: string | null) => {
        const link = props.readyMadeLinks.find((readyMadeLink) => readyMadeLink.key === key)
        if (link) {
            href.value = link.url
        }
    },
})
</script>

<template>
    <div v-if="readyMadeLinks.length" class="py-1.5">
        <div class="flex items-center justify-between gap-x-2">
            <span class="flex items-center gap-x-1 text-[13px] text-gray-600">
                <FontAwesomeIcon :icon="faLink" fixed-width aria-hidden="true" />
                {{ ctrans('Ready-made link') }}
            </span>
            <Select
                v-model="selectedKey"
                :options="readyMadeLinks"
                optionLabel="label"
                optionValue="key"
                :placeholder="ctrans('Insert link')"
                :aria-label="ctrans('Insert a ready-made link')"
                size="small"
                class="w-44 text-[13px]"
            />
        </div>
        <p v-if="selectedLink" class="mt-1 text-[11px] text-gray-500">
            {{ ctrans('Tracking for this link is set automatically.') }}
        </p>
    </div>
</template>
