
import type { Component } from 'vue'

import Footer1Iris from "@/Components/CMS/Website/Footers/footerTheme1/Footer1Iris.vue"
import Header1Iris from "@/Components/CMS/Website/Headers/Header1/Header1Iris.vue"
import Header2Iris from "@/Components/CMS/Website/Headers/Header2/Header2Iris.vue"
import Topbar1Iris from "@/Components/CMS/Website/TopBars/Template/Topbar1/Topbar1Iris.vue"
import Topbar2Iris from "@/Components/CMS/Website/TopBars/Template/Topbar2/Topbar2Iris.vue"
import Topbar3Iris from "@/Components/CMS/Website/TopBars/Template/Topbar3/Topbar3Iris.vue"
import Menu1Workshop from "@/Components/CMS/Website/Menus/Menu1Workshop.vue"
import WowsbarBannerIris from "@/Components/CMS/Webpage/WowsbarBanner/WowsbarBannerIris.vue"
import BentoGridIris from "@/Components/CMS/Webpage/BentoGrid/BentoGridIris.vue"
import GalleryIris from "@/Components/CMS/Webpage/Gallery/GalleryIris.vue"
import CTAIris from "@/Components/CMS/Webpage/Cta1/Cta1Iris.vue"
import CTA2Iris from "@/Components/CMS/Webpage/Cta2/Cta2Iris.vue"
import CTA3Iris from "@/Components/CMS/Webpage/Cta3/Cta3Iris.vue"
import Department1Iris from "@/Components/CMS/Webpage/Department1/Department1Iris.vue"
import IframeIris from "@/Components/CMS/Webpage/Iframe/IframeIris.vue"
import ImageIris from "@/Components/CMS/Webpage/Image/ImageIris.vue"
import OverviewIris from "@/Components/CMS/Webpage/Overview/OverviewIris.vue"
import ScriptIris from "@/Components/CMS/Webpage/Script/ScriptIris.vue"
import TextContentIris from "@/Components/CMS/Webpage/Text/TextContentIris.vue"
import CtaAurora1Iris from "@/Components/CMS/Webpage/CtaAurora1/CtaAurora1Iris.vue"
import Overview2Iris from "@/Components/CMS/Webpage/Overview2/Overview2Iris.vue"
import Pricing from "@/Components/CMS/Webpage/Pricing/PricingIris.vue"
import Timeline from "@/Components/CMS/Webpage/Timeline/TimelineIris.vue"
import TextColumnIris from "@/Components/CMS/Webpage/TextColumn/TextColumnIris.vue"
import Topbar1FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar1Fulfilment/Topbar1FulfilmentIris.vue"
import Topbar2FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar2Fulfilment/Topbar2FulfilmentIris.vue"
import Topbar3FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar3Fulfilment/Topbar3FulfilmentIris.vue"
import ButtonIris from '@/Components/CMS/Webpage/Button/ButtonIris.vue'
import ColumnIris from '@/Components/CMS/Webpage/Column/ColumnIris.vue'
import DisclosureIris from '@/Components/CMS/Webpage/Disclosure/DisclosureIris.vue'
import Step2Iris from '@/Components/CMS/Webpage/Step1/Step1Iris.vue'
import NotFoundComponent from "@/Components/CMS/Webpage/NotFoundComponent.vue"
import FamilyIris1 from '@/Components/CMS/Webpage/Family-1/family1Iris.vue'
import ProductIris1 from '@/Components/CMS/Webpage/Product1/ProductIris1.vue'
import ProductIris1Ecom from '@/Components/CMS/Webpage/Product1/ProductIris1Ecom.vue'
import Carousel1Iris from '@/Components/CMS/Webpage/Carousel-1/Carousel1Iris.vue'
import Products1Iris from '@/Components/CMS/Webpage/Products1/Products1Iris.vue'
import Products1IrisEcom from '@/Components/CMS/Webpage/Products1/Products1IrisEcom.vue'
import SubDepartmentIris from '@/Components/CMS/Webpage/SubDepartment1/SubDepartmentIris.vue'
import Collections1Iris from '@/Components/CMS/Webpage/Collections1/Collections1Iris.vue'
import CTAVideo1Iris from '@/Components/CMS/Webpage/CtaVideo1/CtaVideo1Iris.vue'
import Video1Iris from '@/Components/CMS/Webpage/Video/Video1Iris.vue'
import families1Iris from '@/Components/CMS/Webpage/Families1/FamiliesIris1.vue'
import BlogIris from '@/Components/CMS/Webpage/Blog/BlogIris.vue'
import CarouselCtaIris from '@/Components/CMS/Webpage/CarouselCta/CarouselCtaIris.vue'
import CarouselImageBackgroundIris from '@/Components/CMS/Webpage/CarouselImageBackground/CarouselImageBackgroundIris.vue'

import Cta4 from '@/Components/CMS/Webpage/Cta4/Cta4Iris.vue'

import SeeAlso1WorkshopIris from '@/Components/CMS/Webpage/SeeAlso1/SeeAlso1Iris.vue'
import LuigiTrends1Iris from '@/Components/CMS/Webpage/LuigiTrends1/LuigiTrends1Iris.vue'


import UserSubscribeIris from '@/Components/CMS/Webpage/UserSubscribe/UserSubscribeIris.vue'
import LuigiLastSeen1Iris from '@/Components/CMS/Webpage/LuigiLastSeen1/LuigiLastSeen1Iris.vue'
import LuigiItemAlternatives1Iris from '@/Components/CMS/Webpage/LuigiItemAlternatives1/LuigiItemAlternatives1Iris.vue'

import RecommendationCustomerRecentlyBought1Iris from '@/Components/CMS/Webpage/RecomendationRecentlyBought1/RecommendationCustomerRecentlyBought1Iris.vue'


const components = (shop_type?: string): Record<string, Component> => {
    return {
        //topBar
        'top-bar-1': Topbar1Iris,
        'top-bar-2': Topbar2Iris,
        'top-bar-3': Topbar3Iris,
        'top-bar-1-fulfilment': Topbar1FulfilmentIris,
        'top-bar-2-fulfilment': Topbar2FulfilmentIris,
        'top-bar-3-fulfilment': Topbar3FulfilmentIris,


        //header
        'header-1': Header1Iris,
        'header-2': Header2Iris,

        //menu
        'menu-1': Menu1Workshop,

        //footer
        'footer-1': Footer1Iris,



        //department
        'department' : Department1Iris,
        'department-1' : Department1Iris,

        //sub-department
        'sub-departments-1' : SubDepartmentIris,

        //family
        'family-1' : FamilyIris1,
        'families-1' : families1Iris,

        //product
        'product-1': shop_type === 'b2b' ? ProductIris1Ecom : ProductIris1,


        //product list
        'products-1' : shop_type === 'b2b' ? Products1IrisEcom : Products1Iris,

        //see-also
        'see-also-1' : SeeAlso1WorkshopIris,

        // Luigi
        'luigi-trends-1' : LuigiTrends1Iris,
        'luigi-last-seen-1' : LuigiLastSeen1Iris,
        'luigi-item-alternatives-1' : LuigiItemAlternatives1Iris,
        'recommendation-customer-recently-bought-1': RecommendationCustomerRecentlyBought1Iris,


        'banner': WowsbarBannerIris,
        'bento-grid-1': BentoGridIris,
        'bricks': GalleryIris,
        'cta1': CTAIris,
        'cta2': CTA2Iris,
        'cta3': CTA3Iris,
        'iframe': IframeIris,
        'images': ImageIris,
        'overview_aurora': OverviewIris,
        'script': ScriptIris,
        'text': TextContentIris,
        'cta_aurora_1': CtaAurora1Iris,
        'overview_2': Overview2Iris,
        'text-column': TextColumnIris,
        'pricing': Pricing,
        'button' : ButtonIris,
        'column-layout-2': ColumnIris,
        'disclosure': DisclosureIris,
        'timeline': Timeline,
        'step-1' : Step2Iris,
        'carousel-1' : Carousel1Iris,
        'collections-1': Collections1Iris,
        'cta-video-1' : CTAVideo1Iris,
        'video-1'  : Video1Iris,
        "user-subscribe": UserSubscribeIris,
        "cta4" : Cta4,
        "blog" : BlogIris,
        'carousel-cta' : CarouselCtaIris,
        'carousel-image-background' : CarouselImageBackgroundIris,
    }
}


export const getIrisComponent = (componentName: string, options?: {
    shop_type?: string // 'b2b' | 'dropshipping'
}) => {
    return components(options?.shop_type)[componentName] ?? NotFoundComponent
}
