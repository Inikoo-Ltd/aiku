<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-16
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script lang="ts">
export const supplierPortalBrandLogos = [
	{ name: "ancient-wisdom-logo", alt: "Ancient Wisdom" },
	{ name: "aw-artisan-logo", alt: "AW Artisan" },
	{ name: "aw-gifts-logo", alt: "AW Gifts" },
]
</script>

<script setup lang="ts">
const logos = supplierPortalBrandLogos
</script>

<template>
	<ul class="grid grid-cols-3 items-center gap-8 select-none">
		<li v-for="logo in logos" :key="logo.name" class="flex justify-center">
			<picture>
				<source :srcset="`/art/${logo.name}.avif`" type="image/avif" />
				<img :src="`/art/${logo.name}.webp`" :alt="logo.alt" width="140" height="56" fetchpriority="high" decoding="async" class="h-12 w-full max-w-[8.75rem] object-contain sm:h-14" />
			</picture>
		</li>
	</ul>
</template>
