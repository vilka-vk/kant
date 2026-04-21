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

    return '/' . $relativeDir . '/' . $fileName;
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
