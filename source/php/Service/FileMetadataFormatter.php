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
        $label = str_replace(['-', '_'], ' ', $label);

        return trim($label) !== '' ? ucfirst($label) : $filename;
    }

    public function fileTypeLabel(string $extension): string
    {
        $extension = strtolower($extension);

        $labels = [
            'pdf'  => __('PDF document', 'modularity-folder-browser'),
            'doc'  => __('Word document', 'modularity-folder-browser'),
            'docx' => __('Word document', 'modularity-folder-browser'),
            'xls'  => __('Excel spreadsheet', 'modularity-folder-browser'),
            'xlsx' => __('Excel spreadsheet', 'modularity-folder-browser'),
            'ppt'  => __('PowerPoint presentation', 'modularity-folder-browser'),
            'pptx' => __('PowerPoint presentation', 'modularity-folder-browser'),
            'csv'  => __('CSV file', 'modularity-folder-browser'),
            'txt'  => __('Text file', 'modularity-folder-browser'),
            'jpg'  => __('Image', 'modularity-folder-browser'),
            'jpeg' => __('Image', 'modularity-folder-browser'),
            'png'  => __('Image', 'modularity-folder-browser'),
            'webp' => __('Image', 'modularity-folder-browser'),
        ];

        return $labels[$extension] ?? sprintf(__('%s file', 'modularity-folder-browser'), strtoupper($extension));
    }
}
