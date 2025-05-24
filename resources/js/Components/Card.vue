<script setup lang='ts'>
import { faUser, faCalendarAlt, faEnvelope, faPhone, faGlobe } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faUser, faCalendarAlt, faEnvelope, faPhone, faGlobe)


const props = withDefaults(defineProps<{
    data: {
        title: String;
    };
}>(), {
    data: {
        title: 'Default Title',
    },
})


</script>

<template>
    <div class="w-fit rounded-lg shadow-sm ring-1 ring-gray-500/30 ">
        <dl class="w-fit flex flex-col divide-y divide-gray-900/5">
            <div class="flex justify-between items-end w-full px-6 py-6">
                <div class="flex flex-col items-end">
                    <!-- <dt class="text-sm font-semibold leading-6">Amount</dt> -->
                    <dd :class="[data.title ? 'bg-gradient-to-br from-slate-900 via-purple-800 to-slate-900 bg-clip-text text-transparent mt-1 text-xl font-semibold whitespace-nowrap' : 'italic opacity-50']">
                        <span>{{ data.title }}</span>
                    </dd>
                </div>
            </div>

            <!-- Section: Contact Information -->
            <div class="flex flex-col w-full py-2 text-gray-500">
                    <div> card Content </div>

            </div>
        </dl>
    </div>
</template>