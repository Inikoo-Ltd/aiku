import SwiftUI

extension View {
    func aikuScreen() -> some View {
        background(AikuColor.background.ignoresSafeArea())
            .toolbar(.hidden, for: .navigationBar)
    }

    func card(cornerRadius: CGFloat = 12) -> some View {
        background(AikuColor.surface, in: RoundedRectangle(cornerRadius: cornerRadius))
            .overlay(RoundedRectangle(cornerRadius: cornerRadius).stroke(AikuColor.border))
            .clipShape(RoundedRectangle(cornerRadius: cornerRadius))
    }
}

extension UINavigationController: @retroactive UIGestureRecognizerDelegate {
    override open func viewDidLoad() {
        super.viewDidLoad()
        interactivePopGestureRecognizer?.delegate = self
    }

    public func gestureRecognizerShouldBegin(_ gestureRecognizer: UIGestureRecognizer) -> Bool {
        viewControllers.count > 1
    }
}
