// components
import Corners from "@/Components/Banners/SlidesWorkshop/Fields/Corners/Corners.vue"
import Range from "@/Components/Banners/SlidesWorkshop/Fields/Range.vue"
import Colorpicker from "@/Components/Banners/SlidesWorkshop/Fields/ColorPicker.vue"
import TextAlign from "@/Components/Banners/SlidesWorkshop/Fields/TextAlign.vue"
import SelectFont from "@/Components/Banners/SlidesWorkshop/Fields/SelectFont.vue"
import GradientColor from "@/Components/Banners/SlidesWorkshop/Fields/GradientColor.vue"
import BannerNavigation from "@/Components/Banners/SlidesWorkshop/Fields/BannerNavigation.vue"
/* import Toogle from './Fields/PrimitiveToggle.vue' */
/* import PrimitiveInput from "@/Components/Banners/SlidesWorkshop/Fields/PrimitiveInput.vue"
import Select from "@/Components/Banners/SlidesWorkshop/Fields/PrimitiveSelect.vue" */
import Radio from "@/Components/Banners/SlidesWorkshop/Fields/PrimitiveRadio.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import Toggle from "@/Components/Pure/Toggle.vue"
import SlideBackground from "@/Components/Banners/SlidesWorkshop/Fields/SlideBackground.vue"
import ColorBackground from "@/Components/Banners/Slider/ColorBackground.vue"
import SlideVideo from "@/Components/Banners/SlidesWorkshop/Fields/SlideVideo.vue"
import RangeSlider from "@/Components/Banners/SlidesWorkshop/Fields/RangeSlider.vue"

const componentsMap: Record<string, any> = {
	text: PureInput,
	number: PureInputNumber,
	corners: Corners,
	range: Range,
	rangeSlider: RangeSlider,
	colorpicker: Colorpicker,
	select: PureMultiselect,
	radio: Radio,
	textAlign: TextAlign,
	selectFont: SelectFont,
	toogle: Toggle,
	gradientColor: GradientColor,
	bannerNavigation: BannerNavigation,
	slideBackground: SlideBackground,
	"color-background": ColorBackground,
	slideVideo: SlideVideo,
}

export const getComponent = (componentName: string) => {
	return componentsMap[componentName] ?? PureInput
}
