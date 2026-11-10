import SwiftUI

private struct Pool: Identifiable {
    let id: Int
    let color: UInt32
    let opacity: Double
    let start: CGPoint
    let curves: [CGFloat]
}

private struct PoolShape: Shape {
    let start: CGPoint
    let curves: [CGFloat]

    func path(in rect: CGRect) -> Path {
        let scale = max(rect.width / 400, rect.height / 860)
        let offsetX = rect.midX - 200 * scale
        let offsetY = rect.midY - 430 * scale
        func point(_ x: CGFloat, _ y: CGFloat) -> CGPoint {
            CGPoint(x: offsetX + x * scale, y: offsetY + y * scale)
        }
        var path = Path()
        path.move(to: point(start.x, start.y))
        var index = 0
        while index + 3 < curves.count {
            path.addQuadCurve(
                to: point(curves[index + 2], curves[index + 3]),
                control: point(curves[index], curves[index + 1])
            )
            index += 4
        }
        path.closeSubpath()
        return path
    }
}

struct AuthBackdrop: View {
    private static let pools: [Pool] = [
        Pool(id: 0, color: 0x8B5CF6, opacity: 0.30, start: CGPoint(x: 137, y: 55), curves: [162, 90, 141, 128, 120, 165, 89, 206, 58, 246, 16, 216, -25, 186, -68, 164, -110, 141, -104, 92, -97, 44, -61, 19, -25, -5, 15, -30, 56, -55, 84, -17, 113, 21, 137, 55]),
        Pool(id: 1, color: 0xF3C04A, opacity: 0.30, start: CGPoint(x: 443, y: 4), curves: [441, 40, 432, 67, 423, 93, 401, 117, 378, 141, 337, 146, 296, 150, 289, 109, 281, 69, 278, 39, 275, 9, 289, -25, 303, -60, 343, -76, 383, -92, 415, -62, 446, -32, 443, 4]),
        Pool(id: 2, color: 0x9DCAB0, opacity: 0.32, start: CGPoint(x: 519, y: 521), curves: [566, 560, 514, 595, 463, 630, 437, 682, 411, 733, 363, 702, 316, 672, 293, 636, 270, 600, 271, 560, 272, 521, 293, 484, 315, 447, 362, 419, 410, 390, 441, 437, 472, 483, 519, 521]),
        Pool(id: 3, color: 0x6D63D6, opacity: 0.32, start: CGPoint(x: 178, y: 715), curves: [208, 760, 180, 807, 151, 853, 109, 885, 68, 918, 25, 890, -19, 862, -44, 831, -70, 800, -76, 758, -82, 715, -64, 663, -46, 611, 10, 612, 66, 613, 106, 641, 147, 670, 178, 715]),
        Pool(id: 4, color: 0xB4567A, opacity: 0.29, start: CGPoint(x: 438, y: 788), curves: [444, 830, 427, 863, 410, 897, 382, 931, 354, 964, 309, 954, 265, 943, 250, 903, 236, 864, 226, 826, 215, 788, 243, 758, 271, 727, 313, 709, 355, 690, 393, 718, 431, 745, 438, 788])
    ]

    var body: some View {
        let isDark = ThemeStore.shared.isDark
        ZStack {
            (isDark ? Color(hex: 0x1F1F1F) : Color(hex: 0xFBF7EE))
            ZStack {
                ForEach(Self.pools) { pool in
                    PoolShape(start: pool.start, curves: pool.curves)
                        .fill(Color(hex: pool.color, opacity: pool.opacity))
                }
            }
            .blur(radius: 9)
            .blendMode(isDark ? .screen : .multiply)
            .opacity(isDark ? 0.55 : 1)
        }
        .ignoresSafeArea()
        .accessibilityHidden(true)
    }
}
