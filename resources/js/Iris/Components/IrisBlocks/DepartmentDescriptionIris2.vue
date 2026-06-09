<script setup lang="ts">
import { inject } from 'vue'
import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink, faInfoCircle, faStar, faCircle, faBadgePercent, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock: number
  fieldValue: {
    department: {
      name: string
      description_title?: string
      description?: string
      description_extra?: string
      images: {
        png: string
        avif: string
        webp: string
        original: string
      }
      active_offers: {
      }[]
      offers_data?: {
        [key: string]: {
          state: string
          duration: string
          label: string
          allowances: {
            class: string  // 'discount'
            type: string   // 'percentage_off'
            label: string  // '5.0%'
          }[]
          note: string
        }
      }
    }
  }
}>()


const layout: any = inject("layout", {})


</script>

<template>
  <div :id="fieldValue?.id ? fieldValue?.id : 'department-1-iris' + indexBlock" component="department-1-iris">
    <div :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue?.container?.properties),
      width: 'auto'
    }" class="py-6 px-4 md:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-10">
        <!-- Sidebar -->
        <aside class="hidden lg:block border-r border-gray-300 pr-8">
          <h3 class="font-bold text-lg mb-6">
            Browse By Category:
          </h3>

          <div class="category-scroll max-h-[360px] overflow-y-auto pr-4 space-y-5">
            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Soy Wax Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Aromatherapy Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              White Label Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Dinner Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Scented Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Gemstone Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Tea Light Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Spell Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Candle Holders
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Candle Accessories
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Luxury Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Seasonal Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Crystal Candles
            </a>

            <a href="#" class="block text-[17px] text-slate-700 hover:underline">
              Vegan Candles
            </a>
          </div>
        </aside>

        <!-- Main Content -->
        <section>
          <h1 class="text-[36px] md:text-[52px] font-bold leading-none text-slate-900">
            Wholesale Candles
          </h1>

          <p class="mt-4 text-[15px] leading-6 text-slate-700">
            Whether you're a gift shop owner, event planner, or restaurant manager,
            we've got you covered with our candles wholesale collection. Bring
            beautiful lighting and amazing fragrances to your customers with our
            high-quality candles at great prices!
          </p>

          <!-- Banner -->
          <div class="mt-6 overflow-hidden bg-[#E7E7E7]">
            <div class="grid grid-cols-1 lg:grid-cols-[46%_54%]">
              <!-- Content -->
              <div class="flex flex-col justify-center px-8 lg:px-12 py-8 lg:py-10">
                <h2 class="text-center text-[26px] lg:text-[34px] font-bold text-slate-900">
                  Can You Sell this?
                </h2>

                <div class="mt-6 text-[15px] leading-7 text-slate-700 max-w-[520px] mx-auto">
                  <p>
                    Candles are one of the easiest home fragrance ranges to sell,
                    especially when they have a strong story behind them.
                  </p>

                  <p class="mt-6">
                    From bestselling Ancient Witch Candles to crystal candles and
                    seasonal scent ranges, this category gives retailers plenty of
                    ways to attract gift buyers, home fragrance customers and wellness
                    shoppers.
                  </p>
                </div>

                <div class="flex justify-center mt-8">
                  <button
                    class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-10 py-3 rounded-md transition">
                    Browse All Candles
                  </button>
                </div>
              </div>

              <!-- Image -->
              <div class="overflow-hidden">
                <img src="https://images.unsplash.com/photo-1603006905003-be475563bc59?q=80&w=1600"
                  alt="Wholesale Candles" class="w-full h-[280px] md:h-[320px] lg:h-[360px] object-cover" />
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
<style scoped>
.category-scroll::-webkit-scrollbar {
  width: 6px;
}

.category-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 9999px;
}

.category-scroll::-webkit-scrollbar-track {
  background: transparent;
}
</style>