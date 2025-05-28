<script setup lang="ts">
import { routeType } from '@/types/route';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faImage } from "@far";
import { faInfoCircle } from "@fas";
import Image from "@/Components/Image.vue";
import Message from "primevue/message";
import { Link } from "@inertiajs/vue3";

const props = defineProps<{
  data: {
    family : {
      data : {},
    },
    routeList : {
      collectionRoute : routeType
    },
    routes : {
      detach_family : routeType
    }
  }
}>()

const product = props.data.data

console.log(props.data)
</script>

<template>
  <div class="px-4 pb-8 m-5">

        <Message v-if="data.department?.url_master" severity="success" closable>
            <template #icon>
                <FontAwesomeIcon :icon="faInfoCircle" />
            </template>
            <span class="ml-2">Right Now you follow
        <Link :href="route(data.department.url_master.name,data.department.url_master.parameters)" class="underline font-bold">
        the master data
        </Link>
      </span>
        </Message>


        <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 ">
            <div>
                <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex-1 mx-4">
                            <div class="bg-white rounded-lg shadow hover:shadow-md transition duration-300">
                                <Image v-if="data.family.data?.image" :src="data.family.data?.image" :imageCover="true"
                                       class="w-full h-40 object-cover rounded-t-lg" />
                                <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
                                    <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="border-t pt-4 space-y-4 text-sm text-gray-700">
                        <div class="text-sm font-medium">
                            <span>{{ data.family.data?.name || "No label" }}</span>
                        </div>
                        <div class="text-md">
                            <span class="text-gray-400">{{ data.family.data?.description || "No description" }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
