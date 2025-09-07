<?php

/*
 * Helper functions to Get the correct URL for an attachment files
 * @param string $file_path The file path from database
 * @return string The correct URL for the image
 */
function getImageUrl($file_path)
{
    if (empty($file_path)) {
        return '';
    }

    // * If it's already a full URL then we return as it is
    if (filter_var($file_path, FILTER_VALIDATE_URL)) {
        return $file_path;
    }

    // TODO: Build a basic URL for the script into folder  -> (so that URLs include the project folder or dir when running under a subdirectory)
    $scheme = !empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (!empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
    $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    if ($script_dir === '/') $script_dir = '';
    $base_url = rtrim($scheme . '://' . $host . $script_dir, '/');

    // ! Normalizing path: drop leading slash so we can prepend our project folder
    $normalized = ltrim($file_path, '/');

    //  ! If the path already starts with uploads/ then we keep that otherwise we assume it is a filename and prepend uploads/
    if (strpos($normalized, 'uploads/') !== 0) {
        $normalized = 'uploads/' . $normalized;
    }

    return $base_url . '/' . $normalized;
}

/*
 Todo: Now we need to Check if an image file exists and return it or not if not
 * @param string $file_path The file path from database
 * @return string The image URL or placeholder
 */
function getImageWithFallback($file_path)
{
    // ! If empty
    if (empty($file_path)) return '';

    // ? If it's a full URL then we check the mapped path to DOCUMENT_ROOT
    if (filter_var($file_path, FILTER_VALIDATE_URL)) {
        $parsed = parse_url($file_path);
        $path = $parsed['path'] ?? '';
        $fs = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', DIRECTORY_SEPARATOR) . str_replace('/', DIRECTORY_SEPARATOR, $path);
        return file_exists($fs) ? $file_path : '';
    }

    // ! For relative paths we build similar filesystem path
    $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    if ($script_dir === '/') $script_dir = '';
    $relative = ltrim($file_path, '/');
    if (strpos($relative, 'uploads/') !== 0) {
        $relative = 'uploads/' . $relative;
    }

    $fs_base = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', DIRECTORY_SEPARATOR);
    // ? Script folder to filesystem path : current script running inside the server folder.
    if ($script_dir !== '') {
        $fs_base .= DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $script_dir), DIRECTORY_SEPARATOR);
    }

    $fs_path = $fs_base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (file_exists($fs_path)) {
        return getImageUrl($file_path);
    }

    return '';
}

/*
 * Thumbnail preview with URL for chat attachments
 * @param string $file_path The file path from database
 * @param string $file_type The MIME type of the file
 * @return string The thumbnail URL or file icon
 */
function getChatAttachmentThumbnail($file_path, $file_type)
{
    $image_url = getImageUrl($file_path);

    if (strpos($file_type, 'image/') === 0) {           // If it's an image file, return the image
        return $image_url;
    }

    return ''; //! For non-image files, return a file icon
}

/*
 * Icon class based on file type
 ? @param string $file_type The MIME type of the file -> a standardized label indicating its file format
 * @return string The FontAwesome icons
 */
function getFileIconClass($file_type)
{
    if (strpos($file_type, 'image/') === 0) {
        return 'fas fa-image';
    } elseif (strpos($file_type, 'application/pdf') === 0) {
        return 'fas fa-file-pdf';
    } elseif (strpos($file_type, 'application/msword') === 0 || strpos($file_type, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') === 0) {
        return 'fas fa-file-word';
    } elseif (strpos($file_type, 'text/') === 0) {
        return 'fas fa-file-alt';
    } else {
        return 'fas fa-file';
    }
}
