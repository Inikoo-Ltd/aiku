/* export const getStyles = (properties: any, screens = "desktop") => {
    if (!properties || typeof properties !== 'object') {
        // If properties are missing or not an object, return null
        return null;
    }

    const styles = {
        height: properties?.dimension?.height?.value  ? properties?.dimension?.height?.value  + properties?.dimension?.height?.unit : null,
        width: properties?.dimension?.width?.value ? properties?.dimension?.width?.value + properties?.dimension?.width?.unit : null,
        color: properties?.text?.color || null,
        objectFit : properties?.object_fit|| null,
        objectPosition : properties?.object_position || null,
        fontFamily: properties?.text?.fontFamily || null,
        paddingTop: properties?.padding?.top?.value != null && properties?.padding?.unit ? 
            (properties.padding.top.value + properties.padding.unit) : null,
        paddingBottom: properties?.padding?.bottom?.value != null && properties?.padding?.unit ? 
            (properties.padding.bottom.value + properties.padding.unit) : null,
        paddingRight: properties?.padding?.right?.value != null && properties?.padding?.unit ? 
            (properties.padding.right.value + properties.padding.unit) : null,
        paddingLeft: properties?.padding?.left?.value != null && properties?.padding?.unit ? 
            (properties.padding.left.value + properties.padding.unit) : null,
        marginTop: properties?.margin?.top?.value != null && properties?.margin?.unit ? 
            (properties.margin.top.value + properties.margin.unit) : null,
        marginBottom: properties?.margin?.bottom?.value != null && properties?.margin?.unit ? 
            (properties.margin.bottom.value + properties.margin.unit) : null,
        marginRight: properties?.margin?.right?.value != null && properties?.margin?.unit ? 
            (properties.margin.right.value + properties.margin.unit) : null,
        marginLeft: properties?.margin?.left?.value != null && properties?.margin?.unit ? 
            (properties.margin.left.value + properties.margin.unit) : null,
        background : properties?.background
            ? properties.background.type === 'color'
              ? properties.background.color
              : properties.background.image?.source?.original
              ? `url(${properties.background.image.source.original})`
              : null
            : null,
        borderTop: properties?.border?.top?.value != null && properties?.border?.unit && properties?.border?.color ? 
            `${properties.border.top.value}${properties.border.unit} solid ${properties.border.color}` : null,
        borderBottom: properties?.border?.bottom?.value != null && properties?.border?.unit && properties?.border?.color ? 
            `${properties.border.bottom.value}${properties.border.unit} solid ${properties.border.color}` : null,
        borderRight: properties?.border?.right?.value != null && properties?.border?.unit && properties?.border?.color ? 
            `${properties.border.right.value}${properties.border.unit} solid ${properties.border.color}` : null,
        borderLeft: properties?.border?.left?.value != null && properties?.border?.unit && properties?.border?.color ? 
            `${properties.border.left.value}${properties.border.unit} solid ${properties.border.color}` : null,
        borderTopRightRadius: properties?.border?.rounded?.topright?.value != null && properties?.border?.rounded?.unit ? 
            `${properties.border.rounded.topright.value}${properties.border.rounded.unit}` : null,
        borderBottomRightRadius: properties?.border?.rounded?.bottomright?.value != null && properties?.border?.rounded?.unit ? 
            `${properties.border.rounded.bottomright.value}${properties.border.rounded.unit}` : null,
        borderBottomLeftRadius: properties?.border?.rounded?.bottomleft?.value != null && properties?.border?.rounded?.unit ? 
            `${properties.border.rounded.bottomleft.value}${properties.border.rounded.unit}` : null,
        borderTopLeftRadius: properties?.border?.rounded?.topleft?.value != null && properties?.border?.rounded?.unit ? 
            `${properties.border.rounded.topleft.value}${properties.border.rounded.unit}` : null,
        gap : properties?.gap?.value != null && properties?.gap?.unit ? 
             (properties.gap.value + properties.gap.unit) : null,
        justifyContent : properties?.justifyContent || null,
        boxShadow: getBoxShadowFromParts(properties?.shadow, properties?.shadowColor),
    };
    return Object.fromEntries(Object.entries(styles).filter(([_, value]) => value !== null));
}; */

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

/* export const resolveResponsiveValue = (
    base: any,
    screen: 'mobile' | 'tablet' | 'desktop',
    path?: string[]
  ) => {
    if (!base || typeof base !== 'object') return base;
  
    const responsiveObj = base[screen];
  
    // Jika responsive object ada dan path bernilai, coba ambil dari situ
    if (responsiveObj && typeof responsiveObj === 'object' && path) {
      const resolvedFromResponsive = path.reduce((acc, key) => acc?.[key], responsiveObj);
      if (resolvedFromResponsive !== undefined) return resolvedFromResponsive;
    }
  
    // Fallback ke base biasa
    return path ? path.reduce((acc, key) => acc?.[key], base) : base;
  }; */

  export const resolveResponsiveValue = (
  base: any,
  screen: 'mobile' | 'tablet' | 'desktop',
  path?: string[]
) => {
  if (!base || typeof base !== 'object') return base;

  const getValue = (obj: any) => {
    if (!obj || typeof obj !== 'object') return undefined;
    return path ? path.reduce((acc, key) => acc?.[key], obj) : obj;
  };

  // 1. Coba ambil dari screen saat ini
  const currentValue = getValue(base[screen]);
  if (currentValue !== undefined) return currentValue;

  // 2. Fallback ke desktop jika screen bukan desktop
  if (screen !== 'desktop') {
    const desktopValue = getValue(base.desktop);
    if (desktopValue !== undefined) return desktopValue;
  }

  // 3. Terakhir, fallback ke base global (tanpa screen)
  return getValue(base);
};

  
  

  export const getStyles = (
    properties: any,
    screen: 'mobile' | 'tablet' | 'desktop' = 'desktop'
) => {
    
    if (!properties || typeof properties !== 'object') return null;

    const getVal = (base: any, path?: string[]) =>
        resolveResponsiveValue(base, screen, path);

    const styles: Record<string, string | null> = {
        height: getVal(properties?.dimension, ['height', 'value']) && getVal(properties.dimension, ['height','unit'])
            ? `${getVal(properties.dimension, ['height', 'value'])}${getVal(properties.dimension, ['height','unit'])}`
            : null,

        width: getVal(properties?.dimension, ['width', 'value']) && getVal(properties.dimension, ['width','unit'])
            ? `${getVal(properties.dimension, ['width', 'value'])}${getVal(properties.dimension, ['width','unit'])}`
            : null,

        color:  getVal(properties?.text, ['color']) || null,
        fontFamily:  getVal(properties?.text, ['fontFamily'])  || null,
        fontSize: getVal(properties?.text, ['fontSize']) ? getVal(properties?.text, ['fontSize']) + 'px' : null,
        objectFit: getVal(properties?.object_fit),
        objectPosition: getVal(properties?.object_position),

        paddingTop: getVal(properties?.padding, ['top', 'value']) && getVal(properties.padding, ['unit'])
            ? `${getVal(properties.padding, ['top', 'value'])}${getVal(properties.padding, ['unit'])}`
            : null,

        paddingBottom: getVal(properties?.padding, ['bottom', 'value']) && getVal(properties.padding, ['unit'])
            ? `${getVal(properties.padding, ['bottom', 'value'])}${getVal(properties.padding, ['unit'])}`
            : null,

        paddingLeft: getVal(properties?.padding, ['left', 'value']) && getVal(properties.padding, ['unit'])
            ? `${getVal(properties.padding, ['left', 'value'])}${getVal(properties.padding, ['unit'])}`
            : null,

        paddingRight: getVal(properties?.padding, ['right', 'value']) && getVal(properties.padding, ['unit'])
            ? `${getVal(properties.padding, ['right', 'value'])}${getVal(properties.padding, ['unit'])}`
            : null,

        marginTop: getVal(properties?.margin, ['top', 'value']) && getVal(properties.margin, ['unit'])
            ? `${getVal(properties.margin, ['top', 'value'])}${getVal(properties.margin, ['unit'])}`
            : null,

        marginBottom: getVal(properties?.margin, ['bottom', 'value']) && getVal(properties.margin, ['unit'])
            ? `${getVal(properties.margin, ['bottom', 'value'])}${getVal(properties.margin, ['unit'])}`
            : null,

        marginLeft: getVal(properties?.margin, ['left', 'value']) && getVal(properties.margin, ['unit'])
            ? `${getVal(properties.margin, ['left', 'value'])}${getVal(properties.margin, ['unit'])}`
            : null,

        marginRight: getVal(properties?.margin, ['right', 'value']) && getVal(properties.margin, ['unit'])
            ? `${getVal(properties.margin, ['right', 'value'])}${getVal(properties.margin, ['unit'])}`
            : null,

        // ✅ FIXED RESPONSIVE BACKGROUND
        background: (() => {
            const backgroundBase = properties?.background?.[screen] ?? properties?.background;
            const backgroundType = getVal(backgroundBase, ['type']);
            const backgroundColor = getVal(backgroundBase, ['color']);
            const backgroundGradient = getVal(backgroundBase, ['gradient', 'value']);
            const backgroundImage = getVal(backgroundBase, ['image','source','original']);
            /* console.log(backgroundBase) */
            if (!backgroundType) return null;
            if (backgroundType === 'color') {
                return backgroundColor
            } else if (backgroundType === 'gradient') {
                return backgroundGradient
            } else {
                return backgroundImage ? `url(${backgroundImage})` : null;
            }
        })(),

        borderTop: getVal(properties?.border, ['top', 'value']) && (getVal(properties?.border, ['unit']) || properties?.border?.unit) && (getVal(properties?.border, ['color']) || properties?.border?.color)
            ? `${getVal(properties.border, ['top', 'value'])}${(getVal(properties?.border, ['unit']) || properties.border.unit  )} solid ${(getVal(properties?.border, ['color']) || properties?.border?.color)}`
            : null,

        borderBottom: getVal(properties?.border, ['bottom', 'value']) && (getVal(properties?.border, ['unit']) || properties?.border?.unit) && (getVal(properties?.border, ['color']) || properties?.border?.color)
            ? `${getVal(properties.border, ['bottom', 'value'])}${(getVal(properties?.border, ['unit']) || properties.border.unit  )} solid ${(getVal(properties?.border, ['color']) || properties?.border?.color)}`
            : null,

        borderLeft: getVal(properties?.border, ['left', 'value']) && (getVal(properties?.border, ['unit']) || properties?.border?.unit) && (getVal(properties?.border, ['color']) || properties?.border?.color)
            ? `${getVal(properties.border, ['left', 'value'])}${(getVal(properties?.border, ['unit']) || properties.border.unit  )} solid ${(getVal(properties?.border, ['color']) || properties?.border?.color)}`
            : null,

        borderRight: getVal(properties?.border, ['right', 'value']) && (getVal(properties?.border, ['unit']) || properties?.border?.unit) && (getVal(properties?.border, ['color']) || properties?.border?.color)
            ? `${getVal(properties.border, ['right', 'value'])}${(getVal(properties?.border, ['unit']) || properties.border.unit  )} solid ${(getVal(properties?.border, ['color']) || properties?.border?.color)}`
            : null,

        borderTopLeftRadius: getVal(properties?.border, ['rounded','topleft','value']) && (getVal(properties?.border, ['rounded','unit']) || properties?.border?.rounded?.unit)
            ? `${getVal(properties.border, ['rounded', 'topleft', 'value'])}${(getVal(properties?.border, ['rounded','unit']) || properties.border.rounded.unit  )}`
            : null,

        borderTopRightRadius: getVal(properties?.border, ['rounded','topright','value']) && (getVal(properties?.border, ['rounded','unit']) || properties?.border?.rounded?.unit)
            ? `${getVal(properties.border, ['rounded', 'topright', 'value'])}${(getVal(properties?.border, ['rounded','unit']) || properties.border.rounded.unit  )}`
            : null,

        borderBottomLeftRadius: getVal(properties?.border, ['rounded','bottomleft','value']) && (getVal(properties?.border, ['rounded','unit']) || properties?.border?.rounded?.unit)
            ? `${getVal(properties.border, ['rounded', 'bottomleft', 'value'])}${(getVal(properties?.border, ['rounded','unit']) || properties.border.rounded.unit  )}`
            : null,

        borderBottomRightRadius: getVal(properties?.border, ['rounded','bottomright','value']) && (getVal(properties?.border, ['rounded','unit']) || properties?.border?.rounded?.unit)
            ? `${getVal(properties.border, ['rounded','bottomright', 'value'])}${(getVal(properties?.border, ['rounded','unit']) || properties.border.rounded.unit  )}`
            : null,

        borderColor: getVal(properties?.border, ['color']) && properties?.border?.color
            ? `${getVal(properties.border, ['color'])}`
            : null,

        gap: getVal(properties?.gap, ['value']) && properties?.gap?.unit
            ? `${getVal(properties.gap, ['value'])}${properties.gap.unit}`
            : null,

        justifyContent: getVal(properties?.justifyContent),
        boxShadow: getBoxShadowFromParts(properties?.shadow, properties?.shadowColor)
    };


    return Object.fromEntries(Object.entries(styles).filter(([_, val]) => val !== null));
};