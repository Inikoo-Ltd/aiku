import CoreImage
import CoreImage.CIFilterBuiltins
import UIKit

enum TwoFactorQRCode {
    static let moduleScale: CGFloat = 8
    static let quietZoneModules: CGFloat = 4

    static func image(for setup: TwoFactorSetup) -> UIImage? {
        let generator = CIFilter.qrCodeGenerator()
        generator.message = setup.qrCodeMessage
        generator.correctionLevel = "M"
        guard let modules = generator.outputImage else { return nil }
        let scaled = modules.transformed(by: CGAffineTransform(scaleX: moduleScale, y: moduleScale))
        let margin = moduleScale * quietZoneModules
        let paper = CIImage(color: CIColor.white).cropped(to: scaled.extent.insetBy(dx: -margin, dy: -margin))
        let framed = scaled.composited(over: paper)
        guard let rendered = CIContext().createCGImage(framed, from: framed.extent) else { return nil }
        return UIImage(cgImage: rendered)
    }
}
