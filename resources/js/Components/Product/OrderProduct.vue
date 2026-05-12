<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core";
import { faCheckCircle, faCircle, faQuestionCircle } from "@fal";
import SetOrderingPositionOfProduct from "@/Components/Master/SetOrderingPositionOfProduct.vue";
import Image from "@common/Components/Image.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Icon from "@/Components/Icon.vue"
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faConciergeBell, faGarage, faExclamationTriangle, faPencil, faToolbox, faTools } from "@fal"
import { faTriangle, faEquals, faMinus, faShapes, faStar, faThumbtack, faRunning} from "@fas"


library.add(faConciergeBell, faGarage, faExclamationTriangle, faPencil, faThumbtack, faCircle, faCheckCircle, faQuestionCircle)

const props = defineProps<{
    data: Record<string, any>;
}>();

const emit = defineEmits<{
    (e: "update:data", value: any): void;
}>();

const handleUpdate = (updatedData: any) => {
    emit("update:data", updatedData);
};

const locale = inject("locale", aikuLocaleStructure)

</script>

<template>
    <SetOrderingPositionOfProduct :data="data" @update:data="handleUpdate">
        <template #list-content="{ item }">
            <div class="flex items-center gap-2 w-full py-1">
                <!-- Image -->
                <Image :src="item.image_thumbnail" class="w-8 h-8 object-cover rounded border border-gray-200" />
                <!-- Info -->
                <div class="flex-1 min-w-0 leading-tight">
                    <div class="flex items-center gap-1 text-xs text-gray-800">
                        <Icon :data="item.state" class="text-[10px]" />
                        <span class="truncate font-medium">{{ item.code }}</span>
                    </div>
                    <div class="text-[10px] text-gray-400 truncate">
                        {{ item.name }}
                    </div>
                </div>
                <!-- Right -->
                <div class="text-right leading-tight">
                    <!-- Price -->
                    <div class="text-xs font-semibold text-gray-800">
                        {{ locale.currencyFormat(item.currency_code, item.price) }}
                    </div>
                    <!-- RRP -->
                    <div class="text-[10px] text-gray-400">
                        RRP {{ locale.currencyFormat(item.currency_code, item.rrp) }}
                    </div>
                    <!-- <div class="text-[10px] flex items-center justify-end gap-1">
                        <span class="text-gray-400">Stk</span>
                        <span :class="item.stock > 0 ? 'text-green-600' : 'text-red-500'">
                            {{ item.stock ?? 0 }}
                        </span>
                    </div> -->
                </div>
            </div>
        </template>


        <template #card-content="{ item }">
            <div class="p-1">
                <!-- Image -->
                <Image :src="item.image_thumbnail" class="w-full flex justify-center h-20 object-cover" />

                <!-- Title -->
                <div class="mt-1 text-[10px] font-medium text-gray-800 line-clamp-2 leading-tight">
                    <Icon :data="item.state" class="text-[10px]" /> {{ item.code }}
                </div>

                <!-- Status + Code -->
                <div class="flex items-center justify-between text-xs  text-gray-400 mt-0.5">
                    <div class="flex items-center gap-1 min-w-0">

                        <span class="truncate">{{ item.name }}</span>
                    </div>

                </div>

                <!-- Price -->
                <div class="mt-0.5 flex items-center justify-between text-xs">
                    <div class="font-semibold text-gray-800">
                        {{ locale.currencyFormat(item.currency_code, item.price) }}
                        <span class="text-[10px] text-gray-400 ml-1">
                            (RRP {{ locale.currencyFormat(item.currency_code, item.rrp) }})
                        </span>
                    </div>

                    <!-- <span :class="item.stock > 0 ? 'text-green-600' : 'text-red-500'">
                        {{ item.stock ?? 0 }}
                    </span> -->
                </div>
            </div>
        </template>

    </SetOrderingPositionOfProduct>
</template>