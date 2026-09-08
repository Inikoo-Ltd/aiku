/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { faPercent, faHamburger, faFlowerTulip } from "@fal"

// One place for how tax presets look; the cards, the bulk table and the sweep views all read it.
export const TAX_PRESET_ICONS: Record<string, any> = {
    standard: faPercent,
    food: faHamburger,
    dried_flowers: faFlowerTulip,
    custom: faPercent,
}

export const taxPresetIcon = (value?: string | null) =>
    TAX_PRESET_ICONS[value ?? 'standard'] ?? TAX_PRESET_ICONS.standard
