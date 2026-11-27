import Foundation

@MainActor
protocol LiveSocket: AnyObject {
    func send(_ text: String)
    func close()
}

@MainActor
protocol LiveSocketOpening {
    func open(_ url: URL, onMessage: @escaping @MainActor (String) -> Void, onClose: @escaping @MainActor () -> Void) -> LiveSocket
}

struct URLSessionLiveSocketOpener: LiveSocketOpening {
    func open(_ url: URL, onMessage: @escaping @MainActor (String) -> Void, onClose: @escaping @MainActor () -> Void) -> LiveSocket {
        URLSessionLiveSocket(task: URLSession.shared.webSocketTask(with: url), onMessage: onMessage, onClose: onClose)
    }
}

@MainActor
final class URLSessionLiveSocket: LiveSocket {
    private let task: URLSessionWebSocketTask
    private var receiving: Task<Void, Never>?

    init(task: URLSessionWebSocketTask, onMessage: @escaping @MainActor (String) -> Void, onClose: @escaping @MainActor () -> Void) {
        self.task = task
        task.resume()
        receiving = Task { [task] in
            while !Task.isCancelled {
                do {
                    switch try await task.receive() {
                    case .string(let text):
                        onMessage(text)
                    case .data(let data):
                        onMessage(String(decoding: data, as: UTF8.self))
                    @unknown default:
                        continue
                    }
                } catch {
                    onClose()
                    return
                }
            }
        }
    }

    func send(_ text: String) {
        task.send(.string(text)) { _ in }
    }

    func close() {
        receiving?.cancel()
        task.cancel(with: .normalClosure, reason: nil)
    }
}
