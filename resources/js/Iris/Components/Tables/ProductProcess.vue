<template>
    <div class="grid justify-items-center grid-flow-col auto-cols-auto divide-x-2 divide-gray-200 py-3">
        <div v-for="(fakeLabel, index) of fakeLabels" :key="index" class="w-full"
            :class="{ 'bg-orange-100': fakeLabel.checked }">
            <div class="grid justify-center grid-flow-col items-center focus:bg-gray-800">
                <label :for="(fakeLabel.title + index)" class="py-2 select-none cursor-pointer inline pr-2">
                    {{ fakeLabel.title }} ({{ fakeLabel.total }})
                </label>
                <input :id="(fakeLabel.title + index)" :name="(fakeLabel.title + index)" class="cursor-pointer focus:ring-0"
                    type="checkbox" v-model="fakeLabel.checked" />
                <!-- <div>{{ fakeLabel.checked }}</div> -->
            </div>
        </div>
    </div>
</template>

<script setup>

const fakeLabels = reactive([
    {
        title: "Hired",
        total: 7,
        checked: true,
    },
    {
        title: "On Hold",
        total: 15,
        checked: false,
    },
    {
        title: "On Process",
        total: 24,
        checked: true,
    },
    {
        title: "Finished",
        total: 11,
        checked: false,
    },
])
</script>