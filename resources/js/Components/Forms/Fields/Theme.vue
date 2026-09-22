<script setup>
import {ref} from 'vue'
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';

const selectedTheme = ref("theme-green")
const listTheme = [
    {
        title: "theme-green",
        color: "emerald",
        colorPreview: "bg-emerald-400"
    },
    {
        title: "theme-blue",
        color: "sky",
        colorPreview: "bg-sky-400"
    },
    {
        title: "theme-yellow",
        color: "amber",
        colorPreview: "bg-amber-400"
    },
    {
        title: "theme-purple",
        color: "indigo",
        colorPreview: "bg-indigo-400"
    },
]
</script>
<template>
    <div class="grid gap-y-4 justify-start ">
        <div class="grid grid-flow-col justify-start gap-x-4">
            <div v-for="(theme,index) of listTheme" :key="index" @click="selectedTheme = theme.title"
                class="w-6 h-6 rounded-full cursor-pointer grid justify-center items-center"
                :class="{ 'ring-2 ring-slate-600 ring-offset-2': selectedTheme === theme.title }, theme.colorPreview">
                <FontAwesomeIcon v-if="selectedTheme === theme.title" fixed-width aria-hidden="true" icon="fa-light fa-check"
                    class="text-xs text-slate-800" />
            </div>
        </div>
        <div class="text-[8px] leading-none pt-1 text-gray-500 italic">Theme is for button color.</div>
    </div>
</template>

