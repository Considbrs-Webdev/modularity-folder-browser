<?php

namespace ModularityFolderBrowser\Service;

class FileMetadataFormatter
{
    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return round($value, $power === 0 ? 0 : 1) . ' ' . $units[$power];
    }

    public function formatDate(int $timestamp): string
    {
        return wp_date(get_option('date_format'), $timestamp);
    }

    public function fileLabel(string $filename): string
    {
        $label = pathinfo($filename, PATHINFO_FILENAME);

        return trim($label) !== '' ? ucfirst($label) : $filename;
    }

    public function fileTypeLabel(string $extension): string
    {
        $extension = strtolower($extension);

        $labels = [
            'pdf'  => __('PDF document', 'modularity-folder-browser'),
            'doc'  => __('Word document', 'modularity-folder-browser'),
            'docx' => __('Word document', 'modularity-folder-browser'),
            'odt'  => __('OpenDocument text document', 'modularity-folder-browser'),
            'rtf'  => __('Rich text document', 'modularity-folder-browser'),
            'xls'  => __('Excel spreadsheet', 'modularity-folder-browser'),
            'xlsx' => __('Excel spreadsheet', 'modularity-folder-browser'),
            'ods'  => __('OpenDocument spreadsheet', 'modularity-folder-browser'),
            'ppt'  => __('PowerPoint presentation', 'modularity-folder-browser'),
            'pptx' => __('PowerPoint presentation', 'modularity-folder-browser'),
            'odp'  => __('OpenDocument presentation', 'modularity-folder-browser'),
            'csv'  => __('CSV file', 'modularity-folder-browser'),
            'txt'  => __('Text file', 'modularity-folder-browser'),
            'md'   => __('Markdown file', 'modularity-folder-browser'),
            'jpg'  => __('Image', 'modularity-folder-browser'),
            'jpeg' => __('Image', 'modularity-folder-browser'),
            'png'  => __('Image', 'modularity-folder-browser'),
            'gif'  => __('Image', 'modularity-folder-browser'),
            'webp' => __('Image', 'modularity-folder-browser'),
            'zip'  => __('Archive file', 'modularity-folder-browser'),
            'rar'  => __('Archive file', 'modularity-folder-browser'),
            'gz'   => __('Archive file', 'modularity-folder-browser'),
            '7z'   => __('Archive file', 'modularity-folder-browser'),
            'tar'  => __('Archive file', 'modularity-folder-browser'),
            'mp4'  => __('Video file', 'modularity-folder-browser'),
            'm4v'  => __('Video file', 'modularity-folder-browser'),
            'mov'  => __('Video file', 'modularity-folder-browser'),
            'avi'  => __('Video file', 'modularity-folder-browser'),
            'webm' => __('Video file', 'modularity-folder-browser'),
            'mkv'  => __('Video file', 'modularity-folder-browser'),
            'wmv'  => __('Video file', 'modularity-folder-browser'),
            'mpeg' => __('Video file', 'modularity-folder-browser'),
            'mpg'  => __('Video file', 'modularity-folder-browser'),
            '3gp'  => __('Video file', 'modularity-folder-browser'),
            'ogv'  => __('Video file', 'modularity-folder-browser'),
            'mp3'  => __('Audio file', 'modularity-folder-browser'),
            'wav'  => __('Audio file', 'modularity-folder-browser'),
            'ogg'  => __('Audio file', 'modularity-folder-browser'),
            'oga'  => __('Audio file', 'modularity-folder-browser'),
            'm4a'  => __('Audio file', 'modularity-folder-browser'),
            'aac'  => __('Audio file', 'modularity-folder-browser'),
            'flac' => __('Audio file', 'modularity-folder-browser'),
            'wma'  => __('Audio file', 'modularity-folder-browser'),
            'aiff' => __('Audio file', 'modularity-folder-browser'),
            'aif'  => __('Audio file', 'modularity-folder-browser'),
            'opus' => __('Audio file', 'modularity-folder-browser'),
        ];

        return $labels[$extension] ?? sprintf(__('%s file', 'modularity-folder-browser'), strtoupper($extension));
    }
}
