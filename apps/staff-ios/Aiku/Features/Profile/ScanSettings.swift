import Foundation

enum ScanInputMode: String, CaseIterable, Identifiable {
    case automatic
    case scannerDevice
    case camera

    var id: String { rawValue }

    var title: String {
        switch self {
        case .automatic: "Automatic"
        case .scannerDevice: "Scanner device"
        case .camera: "Camera"
        }
    }

    var detail: String {
        switch self {
        case .automatic: "Scanner device if one is connected, otherwise camera"
        case .scannerDevice: "Built-in or Bluetooth scanner"
        case .camera: "Phone camera with viewfinder"
        }
    }
}

enum ScanSettingsKey {
    static let inputMode = "scan.inputMode"
    static let sound = "scan.sound"
    static let vibrate = "scan.vibrate"
}
