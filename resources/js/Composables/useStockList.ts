export const useBannerBackgroundColor = () => {
    return [
        'rgb(99, 102, 241)', // Indigo
        'rgb(243, 244, 246)', // Gray
        'rgb(41, 37, 36)', // Stone
        'rgb(245, 158, 11)', // Amber
        'linear-gradient(to right, rgb(59, 130, 246), rgb(37, 99, 235))',
        'linear-gradient(to right, rgb(59, 130, 246), rgb(147, 51, 234)',
        'linear-gradient(to right, rgb(244, 63, 94), rgb(248, 113, 113), rgb(239, 68, 68))',
        'linear-gradient(to left bottom, rgb(49, 46, 129), rgb(129, 140, 248), rgb(49, 46, 129))',
        'radial-gradient(at right center, rgb(186, 230, 253), rgb(129, 140, 248))',
        'linear-gradient(to right, rgb(255, 228, 230), rgb(204, 251, 241))',
    ]
}

export const useSolidColor = [
    'rgb(147, 51, 234)', // Purple
    'rgb(239, 68, 68)', // Orange

    'rgb(41, 37, 36)', // Stone
    'rgb(255, 255, 255)', // White
    'rgb(245, 158, 11)', // Amber
    'rgb(20, 184, 166)',  // Teal
    'rgb(51, 65, 85)',  // Slate700
    'rgb(132, 204, 22)',  // Lime
    'rgb(14, 165, 233)',  // Blue
    'rgb(226, 232, 240)',  // Slate200
    'rgb(79, 70, 229)',  // Indigo
    'rgb(219, 39, 119)',  // Pink
]

export const useHeadlineText = () => {
    return [
        'Hello World!',
        'The Industry Standard.',
        'Market Dynamics Overview.',
        'A Business Simulation.',
        'Economic Trends.',
        'Corporate Review.',
        'Industry Analysis.',
        'Case Study.',
        'Market Trends Review.',
    ]
}

// 0-1: Main Layout (bg & text color)
// 2-3: Navigation and box (bg & text color)
// 4-5: Button and mini-box (bg & text color)
export const useColorTheme = [
    ['#4f46e5', '#f3f4f6', '#A9D1E4', '#4f46e5', '#4f46e5', '#f3f4f6'],  // Deep Sea Serenity 
    ['#E5F2F0', '#332925', '#38674F', '#E5F2F0', '#F5D9B9', '#332925'],  // Nature's Embrace
    ['#FFFFFF', '#333333', '#F28B00', '#333333', '#D6C7E2', '#333333'],  // Modern Brights 
    ['#F8F8F8', '#000000', '#2F4F4F', '#F8F8F8', '#424242', '#000000'],  // Classic Sophistication 
    ['#D1EBF2', '#2F4F4F', '#F8F8F8', '#2F4F4F', '#F28B00', '#2F4F4F'],  // Limitless Sky 

    ['#f43f5e', '#F5F5F5', '#E3B7C8', '#000000', '#D6C7E2', '#000000'],  // Rosewater Blush 
    ['#000000', '#f2f2f2', '#424242', '#f2f2f2', '#8e44ad', '#ffffff'],  // Amoled
    ['#f59e0b', '#000000', '#2F2F2F', '#cccccc', '#2F4F4F', '#FFFFFF'],  // Citrus Afterglow 
    ['#1F2937', '#cccccc', '#3F007E', '#cccccc', '#A9D1E4', '#000000'],  // Starlight Sonata 
    ['#84CC16 ', '#000000', '#333333', '#cccccc', '#20C997', '#000000'],  // Tropical Oasis 

    ['#333333', '#cccccc', '#2F4F4F', '#cccccc', '#007bff', '#ffffff'],  // Midnight
    ['#0F1626', '#FFFFFF', '#1e293b', '#f1f5f9', '#0f172a', '#e2e8f0'],  // Black and White (Retina)
    // ['#xxxxxx', '#xxxxxx', '#xxxxxx', '#xxxxxx', '#xxxxxx', '#xxxxxx'],
]