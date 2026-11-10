import SwiftUI

struct ClockingErrorScreen<Extra: View, Actions: View>: View {
    @Environment(AppRouter.self) private var router
    @State private var store = ClockingStore.shared
    let kicker: String
    let icon: SystemStateIcon
    let title: String
    let message: String
    @ViewBuilder var extra: Extra
    @ViewBuilder var actions: (_ backToClocking: @escaping (ClockingPendingAction?) -> Void) -> Actions

    var body: some View {
        SystemStateScreen(showsTabBar: true) {
            SystemStateHeader(title: "Clocking", kicker: kicker, back: .custom { backToClocking(nil) })
        } body: {
            SystemStateBody(icon: icon, title: title, message: Text(message), spacing: 14) {
                extra
            }
        } actions: {
            actions(backToClocking)
        }
    }

    private func backToClocking(_ action: ClockingPendingAction?) {
        store.pendingAction = action
        if store.openedErrorFromClocking {
            router.back()
        } else {
            router.replace(with: ClockingRoute.clocking)
        }
    }
}

struct ClockingQrInvalidView: View {
    @State private var store = ClockingStore.shared

    var body: some View {
        ClockingErrorScreen(
            kicker: "Clocking · QR Code",
            icon: SystemStateIcon(icon: .qrCode, foreground: AikuColor.danger, background: AikuColor.dangerSoft, size: 72, glyph: 34),
            title: "Failed Scan QR",
            message: "The code you scanned was not accepted. The message below comes from Aiku."
        ) {
            Card {
                detailRow("Message", store.rejection.message)
                RowDivider()
                detailRow("Scanned", store.rejection.scanned)
            }
            .multilineTextAlignment(.leading)
        } actions: { back in
            AikuButton("Open camera again") { back(.openCamera) }
                .accessibilityIdentifier("clocking.qrInvalid.camera")
            AikuButton("Back to clocking", kind: .tertiary) { back(nil) }
        }
    }

    private func detailRow(_ label: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(label)
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
            Spacer(minLength: 8)
            Text(value)
                .font(.aiku(13, .semibold))
                .foregroundStyle(AikuColor.text)
                .multilineTextAlignment(.trailing)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
    }
}

struct ClockingLocationBlockedView: View {
    var body: some View {
        ClockingErrorScreen(
            kicker: "Clocking · QR Code",
            icon: SystemStateIcon(icon: .pin, foreground: AikuColor.warn, background: AikuColor.warnSoft, size: 72, glyph: 34),
            title: "Location blocked",
            message: "Please enable location permission in your phone settings. You can still open the camera and scan the machine."
        ) {
            EmptyView()
        } actions: { back in
            AikuButton("Open camera anyway") { back(.chooseWorkplace) }
                .accessibilityIdentifier("clocking.location.cameraAnyway")
            AikuButton("Try again", kind: .tertiary) { back(.detectLocation) }
        }
    }
}

struct ClockingNoPinView: View {
    var body: some View {
        ClockingErrorScreen(
            kicker: "Clocking · PIN",
            icon: SystemStateIcon(icon: .hash, foreground: AikuColor.text3, background: AikuColor.surface, size: 72, glyph: 34),
            title: "No PIN yet",
            message: "No PIN has been generated for you yet. Please contact HR."
        ) {
            Card {
                KeyValueRow("Barcode", "Same message when no barcode exists")
                RowDivider()
                KeyValueRow("Camera QR", "Same message when no QR exists")
            }
            .multilineTextAlignment(.leading)
        } actions: { back in
            AikuButton("Back to clocking") { back(nil) }
                .accessibilityIdentifier("clocking.noPin.back")
        }
    }
}

struct ClockingNoConnectionView: View {
    var body: some View {
        ClockingErrorScreen(
            kicker: "Clocking · QR Code",
            icon: SystemStateIcon(icon: .wifi, foreground: AikuColor.warn, background: AikuColor.warnSoft, size: 72, glyph: 34),
            title: "No connection",
            message: "Aiku checks the machine’s QR code when you scan, so clocking needs a connection. Nothing was saved."
        ) {
            EmptyView()
        } actions: { back in
            AikuButton("Try again") { back(nil) }
                .accessibilityIdentifier("clocking.offline.retry")
        }
    }
}
