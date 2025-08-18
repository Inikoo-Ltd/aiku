<script setup lang="ts">
import { inject, onMounted, onUnmounted, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faFilter, faTimesCircle } from '@fal'
import { faSearchMinus } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
library.add(faFilter, faTimesCircle, faSearchMinus)

// const emit = defineEmits(['resetSearch'])
const emits = defineEmits<{
    (e: 'resetSearch', value: boolean): void
}>()

const layout = inject('layout', layoutStructure)

const props = withDefaults(defineProps<{
    label?: string;
    value?: string;
    onChange: (value: string) => void;
    isVisiting?: boolean;
}>(), {
    label: "Search on table...",
    value: "",
});

// Value on the input field
const querySearch = ref(props.value)


onMounted(() => {
    if (typeof window !== 'undefined') {
        // document.addEventListener('keydown', (e) => e.key == '/' ? document.getElementById("tableinput")?.focus() : false)
        document.addEventListener('keydown', (e) => {
            if (e.key == '/' && e.altKey) {
                document.getElementById("tableinput")?.focus();
            }
        });
        document.addEventListener('keydown', (e) => e.key == 'Escape' ? document.getElementById("tableinput")?.blur() : false);
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', () => false)
})

const isUserMac = navigator.platform.includes('Mac')  // To check the user's Operating System

</script>

<template>
    <div class="rounded-md group relative h-7 flex" v-tooltip="label">
        <input
            id="tableinput"
            placeholder="Type something.."
            :value="querySearch"
            :xxxdisabled="isVisiting"
            type="text"
            name="global"
            @input="(e) => (querySearch = e?.target?.value, onChange(e?.target?.value))"
            class="border border-gray-300 rounded appearance-none inline pl-[60px] lg:pl-[103px] w-0 text-sm leading-none transition-[width] placeholder:text-gray-400 placeholder:italic ring-0 ring-transparent focus:ring-0 focus:ring-transparent cursor-text"
            :class="[querySearch ? 'bg-gray-500 focus:border-gray-500 text-gray-500 w-full pr-8' : 'pr-4 group-focus-within:w-full group-focus-within:pr-4 focus:border-gray-300']"
            :style="{
                backgroundColor: querySearch ? layout?.app?.theme[4] + '33' : '#fff',
                color: querySearch ? `color-mix(in srgb, ${layout?.app?.theme[4]} 50%, black)` : '#000'
            }"
        >
        
        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none space-x-1.5">
            <FontAwesomeIcon icon="far fa-search-minus" class="h-4 w-4" aria-hidden="true"
                :class="[querySearch ? 'text-gray-500' : 'text-gray-400']" 
            />
            <span v-if="isUserMac" class="ring-1 ring-gray-400 bg-gray-100 px-2 leading-none py-0 text-base rounded">⌥</span>
            <span v-else class="ring-1 ring-gray-400 bg-gray-100 px-2 py-0.5 text-xs rounded leading-none">Alt</span>
            <span class="ring-1 ring-gray-400 bg-gray-100 px-2 py-0 text-xs rounded">/</span>
        </div>

        <!-- Button: Reset -->
        <div v-if="isVisiting || querySearch" tabindex="0" class="flex absolute inset-y-0 right-2  items-center pointer-events-auto cursor-pointer" @click="() => (querySearch = '', emits('resetSearch', true))">
            <LoadingIcon v-if="isVisiting" class="h-4 w-4 text-gray-400" />
            <FontAwesomeIcon v-else-if="querySearch" icon="fal fa-times-circle" class="h-4 w-4 text-gray-400" aria-hidden="true" />
        </div>
    </div>
</template>
