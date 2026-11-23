import AVFoundation
import SwiftUI
import Vision
import VisionKit

struct QRCodeScanner: UIViewControllerRepresentable {
    static let warehouseSymbologies: [VNBarcodeSymbology] = [.qr, .code128, .ean13, .ean8, .upce, .code39, .code93, .itf14, .i2of5, .dataMatrix]

    var symbologies: [VNBarcodeSymbology] = [.qr]
    let onCode: (String) -> Void

    init(symbologies: [VNBarcodeSymbology] = [.qr], onCode: @escaping (String) -> Void) {
        self.symbologies = symbologies
        self.onCode = onCode
    }

    static var isAvailable: Bool {
        DataScannerViewController.isSupported && DataScannerViewController.isAvailable
    }

    func makeUIViewController(context: Context) -> DataScannerViewController {
        let scanner = DataScannerViewController(
            recognizedDataTypes: [.barcode(symbologies: symbologies)],
            qualityLevel: .balanced,
            recognizesMultipleItems: false,
            isHighFrameRateTrackingEnabled: false,
            isHighlightingEnabled: true
        )
        scanner.delegate = context.coordinator
        try? scanner.startScanning()
        return scanner
    }

    func updateUIViewController(_ controller: DataScannerViewController, context: Context) {
        context.coordinator.onCode = onCode
    }

    static func dismantleUIViewController(_ controller: DataScannerViewController, coordinator: Coordinator) {
        controller.stopScanning()
    }

    func makeCoordinator() -> Coordinator {
        Coordinator(onCode: onCode)
    }

    final class Coordinator: NSObject, DataScannerViewControllerDelegate {
        var onCode: (String) -> Void
        private var hasDelivered = false

        init(onCode: @escaping (String) -> Void) {
            self.onCode = onCode
        }

        func dataScanner(_ dataScanner: DataScannerViewController, didAdd addedItems: [RecognizedItem], allItems: [RecognizedItem]) {
            guard !hasDelivered else { return }
            for item in addedItems {
                if case .barcode(let barcode) = item, let value = barcode.payloadStringValue, !value.isEmpty {
                    hasDelivered = true
                    dataScanner.stopScanning()
                    ScanFeedback.shared.accepted()
                    onCode(value)
                    return
                }
            }
        }
    }
}

enum CameraPermission {
    static func request() async -> Bool {
        switch AVCaptureDevice.authorizationStatus(for: .video) {
        case .authorized: true
        case .notDetermined: await AVCaptureDevice.requestAccess(for: .video)
        default: false
        }
    }
}
