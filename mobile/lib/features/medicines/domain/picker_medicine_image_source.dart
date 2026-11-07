import 'package:image_picker/image_picker.dart';

import 'medicine_image_acquisition.dart';

/// Uses the platform camera or gallery picker; returns local image paths only.
///
/// The returned path is checked by MedicineImageAcquisition before OCR.
/// Android/iOS host projects and platform permissions must be configured
/// separately. This adapter never uploads the selected image.
class PickerMedicineImageSource implements LocalMedicineImageSource {
  PickerMedicineImageSource({ImagePicker? picker})
    : _picker = picker ?? ImagePicker();

  final ImagePicker _picker;

  @override
  Future<String?> captureImage() async {
    final image = await _picker.pickImage(source: ImageSource.camera);
    return image?.path;
  }

  @override
  Future<String?> chooseLocalImage() async {
    final image = await _picker.pickImage(source: ImageSource.gallery);
    return image?.path;
  }
}
