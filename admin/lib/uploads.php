<?php
declare(strict_types=1);

function upload_public_file(string $fieldName, string $subdir, array $allowedExtensions = []): ?string
{
    if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for "' . $fieldName . '" (error ' . $error . ').');
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid uploaded file for "' . $fieldName . '".');
    }

    $originalName = (string) ($file['name'] ?? 'file');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '') {
        throw new RuntimeException('Uploaded file has no extension for "' . $fieldName . '".');
    }

    if ($allowedExtensions !== [] && !in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('File type "' . $extension . '" is not allowed for "' . $fieldName . '".');
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeBase = trim((string) $safeBase, '-_');
    if ($safeBase === '') {
        $safeBase = 'file';
    }

    $yearMonth = date('Y/m');
    $relativeDir = 'uploads/' . trim($subdir, '/') . '/' . $yearMonth;
    $targetDir = KANT_ROOT . $relativeDir;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Cannot create upload directory: ' . $targetDir);
    }

    $fileName = $safeBase . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $targetDir . '/' . $fileName;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Cannot move uploaded file for "' . $fieldName . '".');
    }
    optimize_uploaded_image($targetPath, $extension);

    return '/' . $relativeDir . '/' . $fileName;
}

function optimize_uploaded_image(string $path, string $extension): void
{
    $ext = strtolower($extension);
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return;
    }
    if (!is_file($path)) {
        return;
    }

    if (class_exists('Imagick')) {
        try {
            $image = new Imagick($path);
            $image->stripImage();
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $image->setImageCompression(Imagick::COMPRESSION_JPEG);
                $image->setImageCompressionQuality(82);
                $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
            } elseif ($ext === 'png') {
                $image->setOption('png:compression-level', '9');
                $image->setOption('png:compression-strategy', '1');
            } elseif ($ext === 'webp') {
                $image->setImageCompressionQuality(82);
            }
            $image->writeImage($path);
            $image->clear();
            $image->destroy();
            return;
        } catch (Throwable $e) {
            // Fallback to GD below.
        }
    }

    if (!function_exists('imagecreatefromjpeg')) {
        return;
    }

    try {
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $img = @imagecreatefromjpeg($path);
            if ($img !== false) {
                @imageinterlace($img, true);
                @imagejpeg($img, $path, 82);
                @imagedestroy($img);
            }
            return;
        }
        if ($ext === 'png' && function_exists('imagecreatefrompng')) {
            $img = @imagecreatefrompng($path);
            if ($img !== false) {
                @imagealphablending($img, false);
                @imagesavealpha($img, true);
                @imagepng($img, $path, 9);
                @imagedestroy($img);
            }
            return;
        }
        if ($ext === 'webp' && function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $img = @imagecreatefromwebp($path);
            if ($img !== false) {
                @imagewebp($img, $path, 82);
                @imagedestroy($img);
            }
        }
    } catch (Throwable $e) {
        // Keep original file if optimization fails.
    }
}

function delete_public_file(string $path): void
{
    $raw = trim($path);
    if ($raw === '') {
        return;
    }

    $normalized = str_replace('\\', '/', $raw);
    if (!str_starts_with($normalized, '/')) {
        $normalized = '/' . $normalized;
    }
    if (!str_starts_with($normalized, '/uploads/')) {
        return;
    }

    $absolutePath = realpath(KANT_ROOT . ltrim($normalized, '/'));
    $uploadsRoot = realpath(KANT_ROOT . 'uploads');
    if ($absolutePath === false || $uploadsRoot === false) {
        return;
    }
    if (!str_starts_with($absolutePath, $uploadsRoot . DIRECTORY_SEPARATOR) && $absolutePath !== $uploadsRoot) {
        return;
    }
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}
