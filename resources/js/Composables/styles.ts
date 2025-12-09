import { computed } from 'vue'

export const getBoxShadowFromParts = (shadowObj: any, color: string) => {
    if (!shadowObj || typeof shadowObj !== 'object') return null;

    const unit = shadowObj.unit || 'px';

    const shadowParts = Object.entries(shadowObj)
        .filter(([key]) => key !== 'unit')
        .map(([key, val]: [string, any]) => {
            const value = val?.value;
            return value != null ? `${value}${unit}` : null;
        })
        .filter(part => part !== null);

    if (shadowParts.length === 0) return null;

    const shadowString = shadowParts.join(' ');
    return color ? `${shadowString} ${color}` : shadowString;
};

export const resolveResponsiveValue = (
  base: any,
  screen: 'mobile' | 'tablet' | 'desktop',
  path?: string[]
) => {
  if (!base || typeof base !== 'object') return base;

  const getValue = (obj: any) => {
		if (!obj || typeof obj !== "object") return undefined
		return path ? path.reduce((acc, key) => acc?.[key], obj) : obj
  }

  // ✅ NEW: If path is undefined and base has direct responsive keys (e.g., { mobile: '...', desktop: '...' }), just return base[screen]
  const isResponsiveObject =
    !path &&
    ['mobile', 'tablet', 'desktop'].some(k => Object.prototype.hasOwnProperty.call(base, k));

  if (isResponsiveObject) {
    return base?.[screen] ?? base?.desktop ?? null;
  }

  // 1. Try current screen
  const currentValue = getValue(base[screen]);
  if (currentValue !== undefined) return currentValue;

  // 2. Fallback to desktop
  if (screen !== 'desktop') {
    const desktopValue = getValue(base.desktop);
    if (desktopValue !== undefined) return desktopValue;
  }

  // 3. Fallback to global
  return getValue(base);
};

  export const getStyles = (
		properties: any,
		screen: string | "mobile" | "tablet" | "desktop" = "desktop",
		useImportant = true
  ) => {
		if (!properties || typeof properties !== "object") return null

		const getVal = (base: any, path?: string[]) => {
			const v = resolveResponsiveValue(base, screen, path)
			return v == undefined || v == null ? null : v
		}

		const isValid = (v: any) => v !== null && v !== undefined;

		const styles: Record<string, string | null> = {
			height:
				getVal(properties?.dimension, ["height", "value"]) &&
				getVal(properties.dimension, ["height", "unit"])
					? `${getVal(properties.dimension, ["height", "value"])}${getVal(
							properties.dimension,
							["height", "unit"]
					  )}`
					: null,

			width:
				getVal(properties?.dimension, ["width", "value"]) &&
				getVal(properties.dimension, ["width", "unit"])
					? `${getVal(properties.dimension, ["width", "value"])}${getVal(
							properties.dimension,
							["width", "unit"]
					  )}`
					: null,

			color: getVal(properties?.text, ["color"]) || null,
			fontFamily: getVal(properties?.text, ["fontFamily"]) || null,
			fontStyle: getVal(properties?.text, ["fontStyle"]) || null,
			textDecoration: getVal(properties?.text, ["textDecoration"]) || null,
			textTransform: getVal(properties?.text, ["textTransform"]) || null,
			fontSize: getVal(properties?.text, ["fontSize"])
				? getVal(properties?.text, ["fontSize"]) + "px"
				: null,
			objectFit: getVal(properties?.object_fit),
			objectPosition: getVal(properties?.object_position),

			paddingTop:
				isValid(getVal(properties?.padding, ["top", "value"])) &&
				isValid(getVal(properties.padding, ["unit"]))
					? `${getVal(properties.padding, ["top", "value"])}${getVal(properties.padding, [
							"unit",
					  ])}`
					: null,

			paddingBottom:
				isValid(getVal(properties?.padding, ["bottom", "value"])) &&
				isValid(getVal(properties.padding, ["unit"]))
					? `${getVal(properties.padding, ["bottom", "value"])}${getVal(
							properties.padding,
							["unit"]
					  )}`
					: null,

			paddingLeft:
				isValid(getVal(properties?.padding, ["left", "value"])) &&
				isValid(getVal(properties.padding, ["unit"]))
					? `${getVal(properties.padding, ["left", "value"])}${getVal(
							properties.padding,
							["unit"]
					  )}`
					: null,

			paddingRight:
				isValid(getVal(properties?.padding, ["right", "value"])) &&
				isValid(getVal(properties.padding, ["unit"]))
					? `${getVal(properties.padding, ["right", "value"])}${getVal(
							properties.padding,
							["unit"]
					  )}`
					: null,

			marginTop:
				isValid(getVal(properties?.margin, ["top", "value"])) &&
				isValid(getVal(properties.margin, ["unit"]))
					? `${getVal(properties.margin, ["top", "value"])}${getVal(properties.margin, [
							"unit",
					  ])}`
					: null,

			marginBottom:
				isValid(getVal(properties?.margin, ["bottom", "value"])) &&
				isValid(getVal(properties.margin, ["unit"]))
					? `${getVal(properties.margin, ["bottom", "value"])}${getVal(
							properties.margin,
							["unit"]
					  )}`
					: null,

			marginLeft:
				isValid(getVal(properties?.margin, ["left", "value"])) &&
				isValid(getVal(properties.margin, ["unit"]))
					? `${getVal(properties.margin, ["left", "value"])}${getVal(properties.margin, [
							"unit",
					  ])}`
					: null,

			marginRight:
				isValid(getVal(properties?.margin, ["right", "value"])) &&
				isValid(getVal(properties.margin, ["unit"]))
					? `${getVal(properties.margin, ["right", "value"])}${getVal(properties.margin, [
							"unit",
					  ])}`
					: null,

			// ✅ FIXED RESPONSIVE BACKGROUND
			background: (() => {
				const backgroundBase = properties?.background?.[screen] ?? properties?.background
				const backgroundType = getVal(backgroundBase, ["type"])
				const backgroundColor = getVal(backgroundBase, ["color"])
				const backgroundGradient = getVal(backgroundBase, ["gradient", "value"])
				const backgroundImage = getVal(backgroundBase, ["image", "source", "original"])
				/* console.log(backgroundBase) */
				if (!backgroundType) return null
				if (backgroundType === "color") {
					return backgroundColor
				} else if (backgroundType === "gradient") {
					return backgroundGradient
				} else {
					return backgroundImage ? `url(${backgroundImage})` : null
				}
			})(),

			backgroundSize: (() => {
				const backgroundBase = properties?.background?.[screen] ?? properties?.background
				const backgroundType = getVal(backgroundBase, ["type"])
				const size = getVal(backgroundBase, ["size"])
				if (backgroundType == "image") return `${size}%`
				return null
			})(),

			backgroundRepeat: (() => {
				const backgroundBase = properties?.background?.[screen] ?? properties?.background
				const backgroundType = getVal(backgroundBase, ["type"])
				const repeat = getVal(backgroundBase, ["repeat"])
				if (backgroundType == "image") return repeat
				return null
			})(),

			backgroundPositionX: (() => {
				const backgroundBase = properties?.background?.[screen] ?? properties?.background
				const backgroundType = getVal(backgroundBase, ["type"])
				const positionX = getVal(backgroundBase, ["positionX"])
				if (backgroundType == "image") return `${positionX}%`
				return null
			})(),

			backgroundPositionY: (() => {
				const backgroundBase = properties?.background?.[screen] ?? properties?.background
				const backgroundType = getVal(backgroundBase, ["type"])
				const positionY = getVal(backgroundBase, ["positionY"])
				if (backgroundType == "image") return `${positionY}%`
				return null
			})(),

			borderTop:
				getVal(properties?.border, ["top", "value"]) &&
				(getVal(properties?.border, ["unit"]) || properties?.border?.unit) &&
				(getVal(properties?.border, ["color"]) || properties?.border?.color)
					? `${getVal(properties.border, ["top", "value"])}${
							getVal(properties?.border, ["unit"]) || properties.border.unit
					  } solid ${getVal(properties?.border, ["color"]) || properties?.border?.color}`
					: null,

			borderBottom:
				getVal(properties?.border, ["bottom", "value"]) &&
				(getVal(properties?.border, ["unit"]) || properties?.border?.unit) &&
				(getVal(properties?.border, ["color"]) || properties?.border?.color)
					? `${getVal(properties.border, ["bottom", "value"])}${
							getVal(properties?.border, ["unit"]) || properties.border.unit
					  } solid ${getVal(properties?.border, ["color"]) || properties?.border?.color}`
					: null,

			borderLeft:
				getVal(properties?.border, ["left", "value"]) &&
				(getVal(properties?.border, ["unit"]) || properties?.border?.unit) &&
				(getVal(properties?.border, ["color"]) || properties?.border?.color)
					? `${getVal(properties.border, ["left", "value"])}${
							getVal(properties?.border, ["unit"]) || properties.border.unit
					  } solid ${getVal(properties?.border, ["color"]) || properties?.border?.color}`
					: null,

			borderRight:
				getVal(properties?.border, ["right", "value"]) &&
				(getVal(properties?.border, ["unit"]) || properties?.border?.unit) &&
				(getVal(properties?.border, ["color"]) || properties?.border?.color)
					? `${getVal(properties.border, ["right", "value"])}${
							getVal(properties?.border, ["unit"]) || properties.border.unit
					  } solid ${getVal(properties?.border, ["color"]) || properties?.border?.color}`
					: null,

			borderTopLeftRadius:
				getVal(properties?.border, ["rounded", "topleft", "value"]) &&
				(getVal(properties?.border, ["rounded", "unit"]) ||
					properties?.border?.rounded?.unit)
					? `${getVal(properties.border, ["rounded", "topleft", "value"])}${
							getVal(properties?.border, ["rounded", "unit"]) ||
							properties.border.rounded.unit
					  }`
					: null,

			borderTopRightRadius:
				getVal(properties?.border, ["rounded", "topright", "value"]) &&
				(getVal(properties?.border, ["rounded", "unit"]) ||
					properties?.border?.rounded?.unit)
					? `${getVal(properties.border, ["rounded", "topright", "value"])}${
							getVal(properties?.border, ["rounded", "unit"]) ||
							properties.border.rounded.unit
					  }`
					: null,

			borderBottomLeftRadius:
				getVal(properties?.border, ["rounded", "bottomleft", "value"]) &&
				(getVal(properties?.border, ["rounded", "unit"]) ||
					properties?.border?.rounded?.unit)
					? `${getVal(properties.border, ["rounded", "bottomleft", "value"])}${
							getVal(properties?.border, ["rounded", "unit"]) ||
							properties.border.rounded.unit
					  }`
					: null,

			borderBottomRightRadius:
				getVal(properties?.border, ["rounded", "bottomright", "value"]) &&
				(getVal(properties?.border, ["rounded", "unit"]) ||
					properties?.border?.rounded?.unit)
					? `${getVal(properties.border, ["rounded", "bottomright", "value"])}${
							getVal(properties?.border, ["rounded", "unit"]) ||
							properties.border.rounded.unit
					  }`
					: null,

			borderColor:
				getVal(properties?.border, ["color"]) && properties?.border?.color
					? `${getVal(properties.border, ["color"])}`
					: null,
			borderWidth:
				getVal(properties?.border, ["width"]) && properties?.border?.width
					? `${getVal(properties.border, ["width", "value"])}${getVal(properties.border, [
							"width",
							"unit",
					  ])}`
					: null,

			gap:
				getVal(properties?.gap, ["value"]) && properties?.gap?.unit
					? `${getVal(properties.gap, ["value"])}${properties.gap.unit}`
					: null,

			display: getVal(properties?.visibility) == "hidden" ? "none" : null,
			justifyContent: getVal(properties.justifyContent),
			boxShadow: getBoxShadowFromParts(properties?.shadow, properties?.shadowColor),
		}

		const data = Object.fromEntries(
			Object.entries(styles)
				.filter(([_, val]) => val !== null && val !== undefined && val !== "undefined")
				.map(([key, val]) => [key, useImportant ? `${val} !important` : `${val}`])
		)


		console.log(getVal(properties?.padding, ["right", "value"]) &&
				getVal(properties.padding, ["unit"])
					? `${getVal(properties.padding, ["right", "value"])}${getVal(
							properties.padding,
							["unit"]
					  )}`
					: null)
		return data
  }


export const useDynamicCssVars = (prefix: string, properties: any, screen: 'mobile' | 'tablet' | 'desktop' = 'desktop') => {
    const styles = getStyles(properties, screen)
    if (!styles) return {}

    const vars: Record<string, string> = {}
    for (const [key, value] of Object.entries(styles)) {
      if (value) {
        vars[`--${prefix}-${key}`] = value.toString().replace('!important', '').trim()
      }
    }
    return vars
}