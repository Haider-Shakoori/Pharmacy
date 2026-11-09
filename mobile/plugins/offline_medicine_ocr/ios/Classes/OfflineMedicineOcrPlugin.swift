import Flutter
import Foundation
import UIKit
import Vision

public class OfflineMedicineOcrPlugin: NSObject, FlutterPlugin {
    public static func register(with registrar: FlutterPluginRegistrar) {
        let channel = FlutterMethodChannel(
            name: "darmaltoon/offline_medicine_ocr",
            binaryMessenger: registrar.messenger()
        )
        registrar.addMethodCallDelegate(OfflineMedicineOcrPlugin(), channel: channel)
    }

    public func handle(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
        switch call.method {
        case "isAvailable":
            result(true)
        case "recognizeLocalImage":
            guard let arguments = call.arguments as? [String: Any],
                  let path = arguments["path"] as? String,
                  !path.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
                result(FlutterError(code: "invalid_path", message: "Local image path required", details: nil))
                return
            }
            recognize(path: path, result: result)
        default:
            result(FlutterMethodNotImplemented)
        }
    }

    private func recognize(path: String, result: @escaping FlutterResult) {
        let url: URL
        if path.hasPrefix("file://") {
            guard let parsed = URL(string: path), parsed.isFileURL,
                  parsed.host == nil || parsed.host == "" else {
                result(FlutterError(code: "invalid_path", message: "Invalid local file URL", details: nil))
                return
            }
            url = parsed
        } else {
            guard path.hasPrefix("/"), !path.contains("://") else {
                result(FlutterError(code: "invalid_path", message: "Remote images are forbidden", details: nil))
                return
            }
            url = URL(fileURLWithPath: path)
        }

        guard FileManager.default.isReadableFile(atPath: url.path) else {
            result(FlutterError(code: "invalid_path", message: "Local image is unreadable", details: nil))
            return
        }

        DispatchQueue.global(qos: .userInitiated).async {
            let request = VNRecognizeTextRequest()
            request.recognitionLevel = .accurate
            request.usesLanguageCorrection = true
            // Ask Vision for every supported target language rather than
            // assuming that the device only recognizes English.
            // Unsupported languages are never passed to the request.
            let preferred = ["fa-IR", "fa-AF", "ps-AF", "en-US"]
            if let supported = try? request.supportedRecognitionLanguages() {
                let selected = preferred.filter { supported.contains($0) }
                if !selected.isEmpty {
                    request.recognitionLanguages = selected
                    request.automaticallyDetectsLanguage = true
                }
            }

            do {
                // Vision reads the file locally and handles supported image orientation.
                let handler = VNImageRequestHandler(url: url, options: [:])
                try handler.perform([request])
                let lines = (request.results ?? []).compactMap {
                    $0.topCandidates(1).first?.string
                }
                DispatchQueue.main.async { result(lines) }
            } catch {
                DispatchQueue.main.async {
                    result(FlutterError(
                        code: "recognition_failed",
                        message: "On-device Vision text recognition failed",
                        details: nil
                    ))
                }
            }
        }
    }
}
