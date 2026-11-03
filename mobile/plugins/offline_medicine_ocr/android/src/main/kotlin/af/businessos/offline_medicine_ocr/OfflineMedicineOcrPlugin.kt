package af.businessos.offline_medicine_ocr

import android.graphics.BitmapFactory
import android.net.Uri
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.text.TextRecognition
import com.google.mlkit.vision.text.latin.TextRecognizerOptions
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.io.File

/**
 * Android-only, bundled Latin-script OCR. No network OCR or model download.
 * Other scripts are NOT supported by this implementation.
 */
class OfflineMedicineOcrPlugin : FlutterPlugin, MethodChannel.MethodCallHandler {
    private var channel: MethodChannel? = null

    override fun onAttachedToEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        channel = MethodChannel(binding.binaryMessenger, "darmaltoon/offline_medicine_ocr")
        channel?.setMethodCallHandler(this)
    }

    override fun onDetachedFromEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        channel?.setMethodCallHandler(null)
        channel = null
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "isAvailable" -> result.success(true)
            "recognizeLocalImage" -> recognize(call, result)
            else -> result.notImplemented()
        }
    }

    private fun recognize(call: MethodCall, result: MethodChannel.Result) {
        val raw = call.argument<String>("path")?.trim()
        if (raw.isNullOrEmpty()) {
            result.error("invalid_path", "Local image path is required", null)
            return
        }
        val path = if (raw.startsWith("file://")) {
            val uri = Uri.parse(raw)
            if (uri.scheme != "file" || !uri.host.isNullOrEmpty()) {
                result.error("invalid_path", "Only local file paths are accepted", null)
                return
            }
            uri.path
        } else {
            raw
        }
        if (path.isNullOrBlank() || raw.contains("://") && !raw.startsWith("file://")) {
            result.error("invalid_path", "Remote image sources are forbidden", null)
            return
        }
        val file = File(path)
        if (!file.isAbsolute || !file.isFile || !file.canRead()) {
            result.error("invalid_path", "Image must be a readable local file", null)
            return
        }
        // Decode bounds first to avoid loading unbounded images into memory.
        val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        BitmapFactory.decodeFile(file.path, bounds)
        if (bounds.outWidth <= 0 || bounds.outHeight <= 0) {
            result.error("invalid_image", "Unable to decode local image", null)
            return
        }
        val maxDimension = maxOf(bounds.outWidth, bounds.outHeight)
        var sample = 1
        while (maxDimension / sample > 2048) sample *= 2
        val bitmap = BitmapFactory.decodeFile(
            file.path,
            BitmapFactory.Options().apply { inSampleSize = sample },
        )
        if (bitmap == null) {
            result.error("invalid_image", "Unable to decode local image", null)
            return
        }
        val recognizer = TextRecognition.getClient(TextRecognizerOptions.DEFAULT_OPTIONS)
        try {
            val image = InputImage.fromBitmap(bitmap, 0)
            recognizer.process(image)
                .addOnSuccessListener { text ->
                    result.success(text.textBlocks.flatMap { block ->
                        block.lines.map { line -> line.text }
                    })
                }
                .addOnFailureListener {
                    result.error("recognition_failed", "Bundled OCR could not read image", null)
                }
                .addOnCompleteListener {
                    recognizer.close()
                    bitmap.recycle()
                }
        } catch (error: Exception) {
            recognizer.close()
            bitmap.recycle()
            result.error("recognition_failed", "Unable to start local OCR", null)
        }
    }
}
