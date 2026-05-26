<?php

namespace ModularityFolderBrowser\AcfFields;

use ModularityFolderBrowser\Service\DirectoryScanner;
use ModularityFolderBrowser\Service\FileMetadataFormatter;
use ModularityFolderBrowser\Service\PathResolver;
use ModularityFolderBrowser\Service\SourceRepository;

class AcfFieldLoader
{
    /**
     * Field keys mapped to their choice loader methods
     */
    private array $fieldChoiceLoaders = [
        'field_modularity_folder_browser_source' => 'getSourceChoices',
        'field_modularity_folder_browser_allowed_file_types' => 'getAllowedExtensionChoices',
    ];

    /**
     * Constructor - register ACF hooks
     */
    public function __construct()
    {
        add_filter('acf/load_field', [$this, 'loadFieldChoices']);
    }

    /**
     * Load field choices based on field key
     *
     * @param array $field The ACF field array
     * @return array Modified field array
     */
    public function loadFieldChoices(array $field): array
    {
        if (!isset($field['key']) || !isset($this->fieldChoiceLoaders[$field['key']])) {
            return $field;
        }

        // Don't load choices when editing ACF field groups
        if ($this->isEditingFieldGroup()) {
            return $field;
        }

        // If no loader method is defined, return field as is
        if (!isset($this->fieldChoiceLoaders[$field['key']])) {
            return $field;
        }

        $loaderMethod = $this->fieldChoiceLoaders[$field['key']];

        if (method_exists($this, $loaderMethod)) {
            $field['choices'] = $this->$loaderMethod();
        }

        return $field;
    }

    /**
     * Check if currently editing an ACF field group
     *
     * @return bool True if editing a field group, false otherwise
     */
    private function isEditingFieldGroup(): bool
    {
        global $pagenow, $typenow;

        // Check if we're on the post edit screen for ACF field groups
        if (in_array($pagenow, ['post.php', 'post-new.php']) && $typenow === 'acf-field-group') {
            return true;
        }

        // Also check via GET/POST parameters as fallback
        $postType = $_GET['post_type'] ?? $_POST['post_type'] ?? null;
        if ($postType === 'acf-field-group') {
            return true;
        }

        // Check if editing an existing field group post
        $postId = $_GET['post'] ?? $_POST['post'] ?? null;
        if ($postId && get_post_type($postId) === 'acf-field-group') {
            return true;
        }

        return false;
    }

    /**
     * Get configured safe filesystem sources for the source select field.
     *
     * @return array<string, string>
     */
    private function getSourceChoices(): array
    {
        return (new SourceRepository())->getSourceChoices();
    }

    /**
     * Get globally allowed file extensions as checkbox choices.
     *
     * @return array<string, string>
     */
    private function getAllowedExtensionChoices(): array
    {
        $sources = new SourceRepository();
        $paths = new PathResolver($sources);
        $scanner = new DirectoryScanner($paths, new FileMetadataFormatter());
        $choices = [];

        foreach ($scanner->getAllowedExtensions(null) as $extension) {
            $choices[$extension] = strtoupper($extension);
        }

        return $choices;
    }
}
