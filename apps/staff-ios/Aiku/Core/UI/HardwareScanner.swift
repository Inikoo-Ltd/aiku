import SwiftUI
import UIKit

final class HardwareScanCaptureView: UIView {
    var onCode: (String) -> Void = { _ in }
    var isListening = false {
        didSet { isListening ? claimKeyboardSoon(takingOver: true) : releaseKeyboard() }
    }

    private var buffer = HardwareScanBuffer()
    private var observers: [NSObjectProtocol] = []

    override init(frame: CGRect) {
        super.init(frame: frame)
        isUserInteractionEnabled = false
        let center = NotificationCenter.default
        let reclaimingEvents: [Notification.Name] = [
            UITextField.textDidEndEditingNotification,
            UITextView.textDidEndEditingNotification,
            UIApplication.didBecomeActiveNotification,
            Self.didStopListening
        ]
        observers = reclaimingEvents.map { name in
            center.addObserver(forName: name, object: nil, queue: .main) { [weak self] _ in
                MainActor.assumeIsolated { self?.claimKeyboardSoon() }
            }
        }
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) is not supported")
    }

    deinit {
        observers.forEach(NotificationCenter.default.removeObserver)
    }

    static let didStopListening = Notification.Name("HardwareScanCaptureView.didStopListening")

    override var canBecomeFirstResponder: Bool { isListening }

    override func didMoveToWindow() {
        super.didMoveToWindow()
        if window == nil {
            NotificationCenter.default.post(name: Self.didStopListening, object: nil)
        } else {
            claimKeyboardSoon(takingOver: true)
        }
    }

    override func pressesBegan(_ presses: Set<UIPress>, with event: UIPressesEvent?) {
        var unhandled: Set<UIPress> = []
        for press in presses {
            guard let key = press.key, key.modifierFlags.isDisjoint(with: [.command, .control, .alternate]) else {
                unhandled.insert(press)
                continue
            }
            switch key.keyCode {
            case .keyboardReturnOrEnter, .keypadEnter, .keyboardTab:
                if let code = buffer.finish(at: .now) {
                    onCode(code)
                }
            default:
                buffer.append(key.characters, at: .now)
            }
        }
        if !unhandled.isEmpty {
            super.pressesBegan(unhandled, with: event)
        }
    }

    private func claimKeyboardSoon(takingOver: Bool = false) {
        DispatchQueue.main.async { [weak self] in
            self?.claimKeyboard(takingOver: takingOver)
        }
    }

    private func claimKeyboard(takingOver: Bool = false) {
        guard isListening, let window, !isFirstResponder else { return }
        let current = window.firstResponderView
        if current is UITextInput { return }
        if current is HardwareScanCaptureView, !takingOver { return }
        becomeFirstResponder()
    }

    private func releaseKeyboard() {
        buffer.clear()
        if isFirstResponder {
            resignFirstResponder()
            NotificationCenter.default.post(name: Self.didStopListening, object: nil)
        }
    }
}

private extension UIView {
    var firstResponderView: UIView? {
        if isFirstResponder { return self }
        for subview in subviews {
            if let responder = subview.firstResponderView {
                return responder
            }
        }
        return nil
    }
}

private struct HardwareScanCapture: UIViewRepresentable {
    let isListening: Bool
    let onCode: (String) -> Void

    func makeUIView(context: Context) -> HardwareScanCaptureView {
        let view = HardwareScanCaptureView()
        view.onCode = onCode
        view.isListening = isListening
        return view
    }

    func updateUIView(_ view: HardwareScanCaptureView, context: Context) {
        view.onCode = onCode
        if view.isListening != isListening {
            view.isListening = isListening
        }
    }
}

private struct HardwareScanner: ViewModifier {
    let isEnabled: Bool
    let onCode: (String) -> Void
    @AppStorage(ScanSettingsKey.inputMode) private var inputMode = ScanInputMode.automatic
    @State private var isVisible = false

    func body(content: Content) -> some View {
        content
            .background {
                HardwareScanCapture(isListening: isVisible && isEnabled && inputMode.listensToScannerDevice, onCode: onCode)
                    .frame(width: 0, height: 0)
                    .accessibilityHidden(true)
            }
            .onAppear { isVisible = true }
            .onDisappear { isVisible = false }
    }
}

extension View {
    func hardwareScanner(isEnabled: Bool = true, onCode: @escaping (String) -> Void) -> some View {
        modifier(HardwareScanner(isEnabled: isEnabled, onCode: onCode))
    }
}
