<script  setup lang="ts">
import PureRadio from '@/Components/Pure/PureRadio.vue'
import { BannerWorkshop } from '@/types/BannerWorkshop'
import { useSolidColor } from '@/Composables/useStockList'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faCheck)


const props = defineProps<{
    fieldName?: string | []
    fieldData?: {
        options: {
            label: string
            name: string
        }[]
    }
    data: BannerWorkshop
}>()

if (!props.data.navigation) {
    props.data.navigation = {
        sideNav: {
            value: true,
            type: 'arrow'
        },
        bottomNav: {
            value: true,
            type: 'bullet'  // button
        }
    }
}

const bottomNavOptions = [
    {
        value: 'bullets'
    },
    {
        value: 'buttons'
    },
]

</script>

<template>
    <div class="mt-2">
        <div class="ml-3 w-fit bg-gray-100 border border-gray-300 rounded px-2 py-2 space-y-2 mb-2">
            <div class="leading-none text-xs text-gray-500">
                Colors
            </div>
            <div class="flex gap-x-1">
                <div v-for="color in useSolidColor" @click="() => data.navigation.colorNav = color"
                    :style="{ 'background-color': color }"
                    class="relative h-5 aspect-square rounded overflow-hidden shadow cursor-pointer transition-all duration-200 ease-in-out"
                    :class="{ 'scale-110': data.navigation?.colorNav == color }"
                >
                    <transition name="slide-bot-to-top">
                        <div v-if="color == data.navigation?.colorNav"
                            class="absolute flex items-center justify-center bg-black/20 inset-0">
                            <FontAwesomeIcon fixed-width icon='fal fa-check' class='text-white' aria-hidden='true' />
                        </div>
                    </transition>
                </div>
            </div>
        </div>
        <table>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="(option, index) in fieldData?.options" :key="index">
                    <td class="whitespace-nowrap px-3 text-sm text-gray-500 text-center flex py-1.5">
                        <input v-model="data.navigation[option.name].value" :id="`item-${index}`" :name="`item-${index}`"
                            type="checkbox" :titles="`I'm Interested in ${option.label}`"
                            class="h-6 w-6 rounded cursor-pointer border-gray-300 hover:border-gray-500 text-gray-600 focus:ring-gray-600" />
                    </td>
                    <td class="">
                        <label :for="`item-${index}`"
                            class="whitespace-nowrap block py-2 pr-3 text-sm font-medium text-gray-500 hover:text-gray-600 cursor-pointer">
                            {{ option.label }}
                        </label>
                        <PureRadio
                            v-if="data.navigation?.[option.name].value && option.name == 'bottomNav'"
                            v-model="data.navigation[option.name].type"
                            :key="`bottomNav-${index}`"
                            :name="`bottomNav-${index}`"
                            :id="`bottomNav-${index}`"
                            :indexChecked="bottomNavOptions.findIndex((item) => item.value == data.navigation[option.name].type.value)"
                            :options="bottomNavOptions"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>