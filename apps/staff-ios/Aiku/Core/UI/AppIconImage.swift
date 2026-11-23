import SwiftUI

extension AppIcon {
    var image: AppIconGlyph { AppIconGlyph(icon: self) }
}

struct AppIconGlyph: View {
    let icon: AppIcon

    var body: some View {
        if let glyph = icon.fontAwesome {
            Image(systemName: "square")
                .hidden()
                .overlay { FontAwesomeShape(glyph: glyph) }
                .accessibilityHidden(true)
        } else {
            Image(systemName: icon.rawValue)
        }
    }
}

struct FontAwesomeShape: Shape {
    let glyph: FontAwesomeGlyph

    func path(in rect: CGRect) -> Path {
        let side = min(rect.width, rect.height)
        let scale = side / CGFloat(glyph.viewport)
        let transform = CGAffineTransform(translationX: rect.midX - side / 2, y: rect.midY - side / 2).scaledBy(x: scale, y: scale)
        return FontAwesomePaths.shared.path(for: glyph).applying(transform)
    }
}

final class FontAwesomePaths: @unchecked Sendable {
    static let shared = FontAwesomePaths()

    private let lock = NSLock()
    private var built: [FontAwesomeGlyph: Path] = [:]

    func path(for glyph: FontAwesomeGlyph) -> Path {
        lock.withLock {
            if let path = built[glyph] { return path }
            var combined = Path()
            for layer in glyph.layers {
                let placement = CGAffineTransform(a: layer.scale, b: 0, c: 0, d: layer.scale, tx: layer.x, ty: layer.y)
                combined.addPath(SVGPathData.path(layer.pathData), transform: placement)
            }
            built[glyph] = combined
            return combined
        }
    }
}
