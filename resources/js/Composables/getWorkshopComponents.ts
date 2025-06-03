import type { Component } from 'vue'

import WowsbarBanner from '@/Components/CMS/Webpage/WowsbarBanner/WowsbarBannerWorkshop.vue'
import ProductPage from '@/Components/CMS/Webpage/ProductPage.vue'
import Text from '@/Components/CMS/Webpage/Text/TextContentWorkshop.vue'
import FamilyPageOffer from '@/Components/CMS/Webpage/FamilyPage-offer.vue'
import ProductList from '@/Components/CMS/Webpage/ProductList.vue'
import CTA from '@/Components/CMS/Webpage/CTA/CTAWorkshop.vue'
import ImageWorkshop from '@/Components/CMS/Webpage/Image/ImageWorkshop.vue'
import CTA2 from '@/Components/CMS/Webpage/CTA2/CTA2Workshop.vue'
import CTA3 from '@/Components/CMS/Webpage/CTA3/CTA3Workshop.vue'
import Gallery from '@/Components/CMS/Webpage/Gallery/GalleryWorkshop.vue'
import Pricing from '@/Components/CMS/Webpage/Pricing/PricingWorkshop.vue'
import Timeline from '@/Components/CMS/Webpage/Timeline/TimelineWorkshop.vue'
import Iframe from '@/Components/CMS/Webpage/Iframe/IframeWorkshop.vue'
import BentoGrid from '@/Components/CMS/Webpage/BentoGrid/BentoGridWorksop.vue'
import Overview from '@/Components/CMS/Webpage/Overview/OverviewWorkshop.vue'
import Script from '@/Components/CMS/Webpage/Script/ScriptWorkShop.vue'
import  CtaAurora1 from "@/Components/CMS/Webpage/CTAAurora1/CtaAurora1Workshop.vue"
import Overview2 from "@/Components/CMS/Webpage/Overview2/Overview2Workshop.vue"
import Footer1 from '@/Components/CMS/Website/Footers/footerTheme1/Footer1Workshop.vue'
import Topbar1 from '@/Components/CMS/Website/TopBars/Template/Topbar1/Topbar1Workshop.vue'
import Topbar2 from '@/Components/CMS/Website/TopBars/Template/Topbar2/Topbar2Workshop.vue'
import Topbar3 from '@/Components/CMS/Website/TopBars/Template/Topbar3/Topbar3Workshop.vue'
import Header1 from '@/Components/CMS/Website/Headers/Header1/Header1Workshop.vue'
import Header2 from '@/Components/CMS/Website/Headers/Header2/Header2Workshop.vue'
import Menu1 from '@/Components/CMS/Website/Menus/Menu1Workshop.vue'
import TextColumn from '@/Components/CMS/Webpage/TextColumn/TextColumnWorkshop.vue'
import Topbar1Fulfilment from '@/Components/CMS/Website/TopBars/Template/Topbar1Fulfilment/Topbar1FulfilmentWorkshop.vue'
import Topbar2Fulfilment from '@/Components/CMS/Website/TopBars/Template/Topbar2Fulfilment/Topbar2FulfilmentWorkshop.vue' 
import Topbar3Fulfilment from '@/Components/CMS/Website/TopBars/Template/Topbar3Fulfilment/Topbar3FulfilemntWorkshop.vue'
import Button from "@/Components/CMS/Webpage/Button/ButtonWorkshop.vue"
import NotFoundComponents from '@/Components/CMS/Webpage/NotFoundComponent.vue'
import ColumnWorkshop from '@/Components/CMS/Webpage/Column/ColumnWorkshop.vue'
import DisclosureWorkshop from '@/Components/CMS/Webpage/Disclosure/DisclosureWorkshop.vue'
import FamilyIris1 from '@/Components/CMS/Webpage/Family1/FamilyIris1.vue'
import Department1Iris from '@/Components/CMS/Webpage/Department1/Department1Iris.vue'
import Step2Workshop from '@/Components/CMS/Webpage/Step1/Step1Workshop.vue'
import Carousel1Workshop from '@/Components/CMS/Webpage/Carousel-1/Carousel1Workshop.vue'
import ProductWorkshop1 from '@/Components/CMS/Webpage/Product1/Product1Workshop.vue'
import SubDepartments1Workshop from '@/Components/CMS/Webpage/SubDepartement1/SubDepartementWorkshop.vue'


export const getComponent = (componentName: string) => {
    const components: Component = {
        //topbar
        'top-bar-1': Topbar1,
        'top-bar-2': Topbar2,
        'top-bar-3': Topbar3,
        'top-bar-1-fulfilment': Topbar1Fulfilment,
        'top-bar-2-fulfilment': Topbar2Fulfilment,
        'top-bar-3-fulfilment': Topbar3Fulfilment,

        //header
        'header-1': Header1,
        'header-2': Header2,

        //menu 
        'menu-1': Menu1,

        //footer
        'footer-1': Footer1,

        //departement
        'department' : Department1Iris,

        //sub-departement
        'sub-departments-1' : SubDepartments1Workshop,

        //family


        //product
        'product-1': ProductWorkshop1,
        'product': ProductWorkshop1,

        //product list
        'products': ProductList,

        'banner': WowsbarBanner,
        "bento-grid-1": BentoGrid,
        "bricks": Gallery,
        'cta1': CTA,
        'cta2': CTA2,
        'cta3': CTA3,
        'family-1': FamilyIris1,
        'families-1': FamilyIris1,
        "iframe": Iframe,
        'images': ImageWorkshop,
        "overview_aurora": Overview,
        "script": Script,
        'text': Text,
        'cta_aurora_1' : CtaAurora1,
        'overview_2' : Overview2, 
        'text-column' : TextColumn,
        'pricing': Pricing,
        'button' : Button,
        'column-layout-2': ColumnWorkshop,
        'disclosure': DisclosureWorkshop,
        'timeline' : Timeline,
        'step-1' : Step2Workshop,
        'carousel-1' : Carousel1Workshop
    }
    return components[componentName] ?? NotFoundComponents
}

