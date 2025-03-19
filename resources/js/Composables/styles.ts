export const getStyles = (properties: any) => {
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
    };

    return Object.fromEntries(Object.entries(styles).filter(([_, value]) => value !== null));
};

