<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 02 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserHardHat, faTimes } from "@fal"

library.add(faUserHardHat, faTimes)

type Artisan = { id: number, name: string }

const props = defineProps<{
    data: {
        current: Artisan[]
        options: Artisan[]
        attach_route: { name: string, parameters: (number | string)[] } | null
        detach_route: { name: string, parameters: (number | string)[] } | null
    }
}>()

const adding = ref<number | "">("")
const available = computed(() => props.data.options.filter(option => !props.data.current.some(artisan => artisan.id === option.id)))

function attach() {
    if (adding.value === "" || !props.data.attach_route) return
    router.post(route(props.data.attach_route.name, props.data.attach_route.parameters), { employee_id: adding.value }, {
        preserveScroll: true,
        onSuccess: () => { adding.value = "" },
    })
}

function detach(artisan: Artisan) {
    if (!props.data.detach_route) return
    router.delete(route(props.data.detach_route.name, [...props.data.detach_route.parameters, artisan.id]), { preserveScroll: true })
}
</script>

<template>
    <div class="mx-4 mt-4 flex flex-wrap items-center gap-2 text-sm">
        <span class="flex items-center gap-1.5 text-gray-500">
            <FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width aria-hidden="true" />
            {{ trans("Usually made by") }}
        </span>
        <span v-if="!data.current.length" class="text-gray-400">{{ trans("nobody yet") }}</span>
        <span
            v-for="(artisan, index) in data.current"
            :key="artisan.id"
            class="flex items-center gap-1 rounded-full border px-2.5 py-px text-xs"
            :class="index === 0 ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600'">
            {{ artisan.name }}
            <button v-if="data.detach_route" type="button" class="text-gray-400 hover:text-red-600" :title="trans('Remove')" @click="detach(artisan)">
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
            </button>
        </span>
        <select v-if="data.attach_route && available.length" v-model="adding" class="rounded border-gray-300 py-0.5 text-xs" @change="attach">
            <option value="">{{ trans("Add artisan") }}…</option>
            <option v-for="option in available" :key="option.id" :value="option.id">{{ option.name }}</option>
        </select>
    </div>
</template>
