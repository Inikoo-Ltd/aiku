<script setup lang='ts'>
import Editmodel from '@/Pages/Grp/EditModel.vue'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { onMounted, ref } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import LoadingText from '@/Components/Utils/LoadingText.vue'
library.add(faSpinnerThird)

defineProps<{
    embedded?: boolean
}>()

const dataEditProfile = ref<{} | null>(null)
onMounted(async () => {
    try {
        const { data } = await axios.get(route('grp.profile.edit'))
        dataEditProfile.value = data
    } catch (error) {
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to fetch this page.'),
            type: 'error',
        })
    }
})
</script>

<template>
    <Editmodel v-if="dataEditProfile" v-bind="dataEditProfile" :embedded="embedded" />

    <div v-else-if="embedded" class="animate-pulse lg:grid lg:grid-cols-12" role="status" :aria-label="trans('Loading personal settings')">
        <div class="hidden lg:block lg:col-span-3 space-y-3 border-r border-gray-200 bg-gray-50/50 p-4">
            <div v-for="row in 4" :key="`nav-${row}`" class="h-5 rounded bg-gray-200" :class="row % 2 ? 'w-2/3' : 'w-1/2'" />
        </div>
        <div class="lg:col-span-9 space-y-6 p-6">
            <div v-for="row in 3" :key="`field-${row}`" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="h-4 w-24 rounded bg-gray-200" />
                <div class="sm:col-span-2 h-10 rounded-md bg-gray-100" />
            </div>
        </div>
    </div>

    <div v-else class="h-full flex flex-col gap-y-2 items-center justify-center">
        <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin' size="2x" fixed-width aria-hidden='true' />
        <LoadingText />
    </div>
</template>