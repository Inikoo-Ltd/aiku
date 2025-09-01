<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileCsv } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import CopyButton from "@/Components/Utils/CopyButton.vue"
library.add(faFileCsv)

const props = defineProps<{
    routeApi: string
}>()
</script>

<template>
    <!-- Box: API URL -->
    <div class="mb-6 w-fit border border-gray-300 rounded overflow-hidden">
        <div class="py-1 px-3 text-base font-bold bg-gray-100 ">
            <FontAwesomeIcon icon="fal fa-file-csv" class="opacity-70" fixed-width aria-hidden="true" />
            Data feed Url
            <!-- <InformationIcon
                :information="trans('Download all products, can be used to integrate with 3rd party app')"
            /> -->
            :
        </div>
        
        <div class="px-3 border-t border-gray-300 overflow-hidden flex items-center text-sm md:text-base text-gray-500">
            <a :href="routeApi" class="leading-none hover:underline hover:text-blue-700 py-5 px-2 w-full italic text-lg">
                {{ routeApi }}
            </a>
            <div v-tooltip="trans('Copy url')" class="flex items-center justify-center relative">
                <CopyButton
                    :text="routeApi"
                    class="xtext-3xl px-2 py-1.5"
                />
            </div>
        </div>
    </div>
</template>