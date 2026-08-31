const palette = ['#8b5cf6', '#76b7c2', '#f3c04a', '#e88a73', '#9dcab0', '#b4567a', '#6d63d6']

const rand = (min: number, max: number) => min + Math.random() * (max - min)

const blob = (cx: number, cy: number, r: number) => {
    const points = 9
    const pts = Array.from({ length: points }, (_, i) => {
        const angle = (i / points) * Math.PI * 2
        const radius = r * rand(0.7, 1.25)
        return [cx + Math.cos(angle) * radius, cy + Math.sin(angle) * radius]
    })
    const mid = (a: number[], b: number[]) => [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2]
    let d = `M${mid(pts[points - 1], pts[0]).map(v => v.toFixed(0)).join(' ')} `
    pts.forEach((pt, i) => {
        const next = pts[(i + 1) % points]
        d += `Q${pt[0].toFixed(0)} ${pt[1].toFixed(0)} ${mid(pt, next).map(v => v.toFixed(0)).join(' ')} `
    })
    return d + 'Z'
}

export const watercolourBackground = (): string => {
    const seed = Math.floor(rand(1, 999))
    const colours = [...palette].sort(() => Math.random() - 0.5)
    const pools = Array.from({ length: 5 }, (_, i) =>
        `<path d="${blob(rand(-100, 1700), rand(-100, 1100), rand(220, 420))}" fill="${colours[i]}" opacity="${rand(0.26, 0.36).toFixed(2)}"/>`
    ).join('')

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid slice">
<defs>
<filter id="b" x="-30%" y="-30%" width="160%" height="160%"><feTurbulence type="fractalNoise" baseFrequency="0.005 0.008" numOctaves="3" seed="${seed}" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="70" xChannelSelector="R" yChannelSelector="G" result="d"/><feGaussianBlur in="d" stdDeviation="6" result="bl"/><feTurbulence type="fractalNoise" baseFrequency="0.03" numOctaves="3" seed="${seed + 1}" result="g"/><feColorMatrix in="g" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.9 -0.2" result="ga"/><feComposite in="bl" in2="ga" operator="in" result="m"/><feMerge><feMergeNode in="bl"/><feMergeNode in="m"/></feMerge></filter>
<filter id="p" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" seed="${seed + 2}" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0.86 0 0 0 0 0.82 0 0 0 0 0.72 0 0 0 0.55 -0.12" result="grain"/><feTurbulence type="fractalNoise" baseFrequency="0.012 0.9" numOctaves="2" seed="${seed + 3}" result="f"/><feColorMatrix in="f" type="matrix" values="0 0 0 0 0.80 0 0 0 0 0.75 0 0 0 0 0.64 0 0 0 0.7 -0.45" result="fibres"/><feMerge><feMergeNode in="SourceGraphic"/><feMergeNode in="grain"/><feMergeNode in="fibres"/></feMerge></filter>
</defs>
<rect width="1600" height="1000" fill="#fbf7ee"/><rect width="1600" height="1000" fill="#fbf7ee" filter="url(#p)"/>
<g filter="url(#b)" style="mix-blend-mode:multiply">${pools}</g></svg>`

    return `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`
}
