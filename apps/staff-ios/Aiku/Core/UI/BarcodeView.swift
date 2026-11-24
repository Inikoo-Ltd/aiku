import CoreImage
import CoreImage.CIFilterBuiltins
import SwiftUI
import UIKit

enum LinearBarcodeModules {
    static func of(_ value: String) -> [Bool]? {
        switch BarcodeEncoding.format(of: value) {
        case .ean13: BarcodeEncoding.ean13Modules(value)
        case .code128: code128(value)
        }
    }

    private static func code128(_ value: String) -> [Bool]? {
        guard let message = BarcodeEncoding.code128Message(value) else { return nil }
        let generator = CIFilter.code128BarcodeGenerator()
        generator.message = message
        generator.quietSpace = 0
        guard let bars = generator.outputImage else { return nil }
        let paper = CIImage(color: CIColor.white).cropped(to: bars.extent)
        let image = bars.composited(over: paper)
        let width = Int(image.extent.width)
        guard width > 0 else { return nil }
        var pixels = [UInt8](repeating: 255, count: width * 4)
        let firstRow = CGRect(x: image.extent.minX, y: image.extent.minY, width: CGFloat(width), height: 1)
        CIContext().render(image, toBitmap: &pixels, rowBytes: width * 4, bounds: firstRow, format: .RGBA8, colorSpace: nil)
        let darkness = stride(from: 0, to: pixels.count, by: 4).map { pixels[$0] < 128 }
        return BarcodeEncoding.modules(fromDarkness: darkness)
    }
}

struct LinearBarcodeView: View {
    let modules: [Bool]
    var barHeight: CGFloat = 120

    static let ink = Color(hex: 0x000000)
    static let paper = Color(hex: 0xFFFFFF)

    var body: some View {
        let bars = modules
        let ink = Self.ink
        Canvas { context, size in
            guard !bars.isEmpty else { return }
            let moduleWidth = size.width / CGFloat(bars.count)
            for (index, isBar) in bars.enumerated() where isBar {
                let bar = CGRect(x: CGFloat(index) * moduleWidth, y: 0, width: moduleWidth, height: size.height)
                context.fill(Path(bar), with: .color(ink))
            }
        }
        .frame(maxWidth: .infinity)
        .frame(height: barHeight)
    }
}

@MainActor
private struct FullScreenBrightness: ViewModifier {
    @State private var previousBrightness: CGFloat?

    func body(content: Content) -> some View {
        content
            .onAppear {
                guard let screen = Self.activeScreen else { return }
                previousBrightness = screen.brightness
                screen.brightness = 1
            }
            .onDisappear {
                guard let previousBrightness, let screen = Self.activeScreen else { return }
                screen.brightness = previousBrightness
                self.previousBrightness = nil
            }
    }

    private static var activeScreen: UIScreen? {
        let scenes = UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }
        return (scenes.first { $0.activationState == .foregroundActive } ?? scenes.first)?.screen
    }
}

extension View {
    func fullScreenBrightness() -> some View {
        modifier(FullScreenBrightness())
    }
}
