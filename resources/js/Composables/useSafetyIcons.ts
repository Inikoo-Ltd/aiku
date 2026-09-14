import doNotBlowOutCandle from '@/../art/safety-icons/do-not-blow-out-candle.png'
import doNotExtinguishWithWater from '@/../art/safety-icons/do-not-extinguish-with-water.png'
import doNotLeaveUnattended from '@/../art/safety-icons/do-not-leave-unattended.png'
import doNotMoveBurningCandle from '@/../art/safety-icons/do-not-move-burning-candle.png'
import doNotPlaceCandlesCloseTogether from '@/../art/safety-icons/do-not-place-candles-close-together.png'
import extinguishByDippingWick from '@/../art/safety-icons/extinguish-by-dipping-wick.png'
import keep10cmBetweenCandles from '@/../art/safety-icons/keep-10cm-between-candles.png'
import keepAwayFromChildrenAndPets from '@/../art/safety-icons/keep-away-from-children-and-pets.png'
import keepAwayFromDraughts from '@/../art/safety-icons/keep-away-from-draughts.png'
import keepAwayFromFlammableMaterials from '@/../art/safety-icons/keep-away-from-flammable-materials.png'
import keepAwayFromHeatSources from '@/../art/safety-icons/keep-away-from-heat-sources.png'
import placeOnStableSurface from '@/../art/safety-icons/place-on-stable-surface.png'
import trimWickTo1cm from '@/../art/safety-icons/trim-wick-to-1cm.png'
import useHeatResistantCandleHolder from '@/../art/safety-icons/use-heat-resistant-candle-holder.png'
import useVentilatedTeapotWarmer from '@/../art/safety-icons/use-ventilated-teapot-warmer.png'
import warningTriangle from '@/../art/safety-icons/warning-triangle.png'
export interface SafetyIcon {
    key: string
    label: string
    image: string
}

export const candleSafetyIcons: SafetyIcon[] = [
    { key: 'warning_triangle', label: 'General warning', image: warningTriangle },
    { key: 'do_not_leave_unattended', label: 'Never leave a burning candle unattended', image: doNotLeaveUnattended },
    { key: 'keep_away_from_children_and_pets', label: 'Keep away from children and pets', image: keepAwayFromChildrenAndPets },
    { key: 'keep_away_from_flammable_materials', label: 'Keep away from flammable materials', image: keepAwayFromFlammableMaterials },
    { key: 'keep_away_from_draughts', label: 'Keep away from draughts', image: keepAwayFromDraughts },
    { key: 'keep_away_from_heat_sources', label: 'Keep away from heat sources', image: keepAwayFromHeatSources },
    { key: 'place_on_stable_surface', label: 'Place on a stable heat resistant surface', image: placeOnStableSurface },
    { key: 'use_heat_resistant_candle_holder', label: 'Use a heat resistant candle holder', image: useHeatResistantCandleHolder },
    { key: 'keep_10cm_between_candles', label: 'Leave at least 10 cm between candles', image: keep10cmBetweenCandles },
    { key: 'do_not_place_candles_close_together', label: 'Do not place candles close together', image: doNotPlaceCandlesCloseTogether },
    { key: 'trim_wick_to_1cm', label: 'Trim the wick to 1 cm before lighting', image: trimWickTo1cm },
    { key: 'do_not_move_burning_candle', label: 'Never move a burning candle', image: doNotMoveBurningCandle },
    { key: 'do_not_blow_out_candle', label: 'Do not blow out the candle', image: doNotBlowOutCandle },
    { key: 'extinguish_by_dipping_wick', label: 'Extinguish by dipping the wick into the wax', image: extinguishByDippingWick },
    { key: 'do_not_extinguish_with_water', label: 'Do not extinguish with water', image: doNotExtinguishWithWater },
    { key: 'use_ventilated_teapot_warmer', label: 'Use only a ventilated teapot warmer', image: useVentilatedTeapotWarmer },
]
