<script setup lang='ts'>
import { Link } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUpload } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faUpload)

const props = defineProps<{
    data: any
}>()

</script>

<template>
    <div class="rounded overflow-hidden ring-1 ring-gray-400 flex">
        <template v-for="(button, index) in data">
            <Link
                :href="`${route(button.route.name, button.route.parameters)}`" class=""
                :method="button.method ?? 'get'"
            >
                <Button :style="button.style" :label="button.label" :icon="button.icon"
                    class="inline-flex items-center h-full rounded-none text-sm border-none font-medium shadow-sm focus:ring-transparent focus:ring-offset-transparent focus:ring-0">
                </Button>
            </Link>
        </template>
    </div>
</template>
