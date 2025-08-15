
export const breakpointType = (screen?: number) => {
    if(screen?.width < 639) {
        return 'xs'
    } else if(639 < screen?.width && screen?.width < 768){
        return 'sm';
    } else if (767 < screen?.width && screen?.width < 1024) {
        return 'md';
    } else if(1023 < screen?.width && screen?.width < 1280) {
        return 'lg';
    } else if(1279 < screen?.width && screen?.width < 1536) {
        return 'xl';
    } else if(1535 < screen?.width) {
        return '2xl';
    }
}

export const twBreakPoint = () => {
    let screen = 0
    if (window) {
        screen = window.innerWidth
    }

    let type = []
    
    if (screen >= 640) {
        type.push('sm')
    }

    if (screen >= 768) {
        type.push('md')
    }

    if (screen >= 1024) {
        type.push('lg')
    }

    if (screen >= 1280) {
        type.push('xl')
    }

    if (screen >= 1536) {
        type.push('2xl')
    }

    return type
}