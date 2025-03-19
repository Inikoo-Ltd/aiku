
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
