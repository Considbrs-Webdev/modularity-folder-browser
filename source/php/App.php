<?php

namespace ModularityFolderBrowser;

use ModularityFolderBrowser\AcfFields\AcfFieldLoader;
use ModularityFolderBrowser\Helper\CacheBust;
use ModularityFolderBrowser\Helper\IconResolver;
use ModularityFolderBrowser\Rest\RestController;

class App
{
    public function __construct()
    {
        // Register module
        add_action('init', array($this, 'registerModule'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueueStyles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));
        add_action('acf/input/admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));

        // Load ACF field values
        new AcfFieldLoader();
        new RestController();
    }

    /**
     * Enqueue styles
     * @return void
     */
    public function enqueueStyles()
    {
        $styleFile = CacheBust::name('css/modularity-folder-browser.css');

        if ($styleFile) {
            wp_enqueue_style(
                'modularity-folder-browser',
                MODULARITY_FOLDER_BROWSER_URL . '/assets/dist/' . $styleFile,
                array(),
                null
            );
        }
    }

    /**
     * Enqueue scripts
     * @return void
     */
    public function enqueueScripts()
    {
        $scriptFile = CacheBust::name('js/modularity-folder-browser.js');

        if ($scriptFile) {
            wp_enqueue_script(
                'modularity-folder-browser',
                MODULARITY_FOLDER_BROWSER_URL . '/assets/dist/' . $scriptFile,
                array(),
                null,
                true
            );

            wp_localize_script('modularity-folder-browser', 'ModularityFolderBrowser', array(
                'restUrl' => esc_url_raw(rest_url('modularity-file-browser/v1/')),
                'i18n' => array(
                    'loading' => __('Loading folder contents.', 'modularity-folder-browser'),
                    'error' => __('This folder could not be loaded.', 'modularity-folder-browser'),
                    'empty' => __('No documents found.', 'modularity-folder-browser'),
                    'folder' => __('Folder', 'modularity-folder-browser'),
                    'download' => __('Download', 'modularity-folder-browser'),
                    'searching' => __('Searching documents.', 'modularity-folder-browser'),
                    'searchError' => __('The search could not be completed.', 'modularity-folder-browser'),
                    'searchEmpty' => __('No matching files or folders found.', 'modularity-folder-browser'),
                    /* translators: %d: result count. */
                    'searchResults' => __('%d matching files or folders found.', 'modularity-folder-browser'),
                    /* translators: %s: file name. */
                    'downloadFile' => __('Download %s', 'modularity-folder-browser'),
                ),
                'icons' => IconResolver::getIconMap(),
                'iconCategories' => IconResolver::getCategoryMap(),
            ));
        }
    }

    /**
     * Enqueue admin folder picker script.
     * @return void
     */
    public function enqueueAdminScripts()
    {
        $styleFile = CacheBust::name('css/modularity-folder-browser.css');
        $scriptFile = CacheBust::name('js/modularity-folder-browser-admin.js');

        if ($styleFile) {
            wp_enqueue_style(
                'modularity-folder-browser-admin',
                MODULARITY_FOLDER_BROWSER_URL . '/assets/dist/' . $styleFile,
                array(),
                null
            );
        }

        if ($scriptFile) {
            wp_enqueue_script(
                'modularity-folder-browser-admin',
                MODULARITY_FOLDER_BROWSER_URL . '/assets/dist/' . $scriptFile,
                array('acf-input'),
                null,
                true
            );

            wp_localize_script('modularity-folder-browser-admin', 'ModularityFolderBrowserAdmin', array(
                'restUrl' => esc_url_raw(rest_url('modularity-file-browser/v1/admin/folders')),
                'nonce' => wp_create_nonce('wp_rest'),
                'i18n' => array(
                    'selectFolders' => __('Select folders', 'modularity-folder-browser'),
                    'dialogTitle' => __('Select folders to expose', 'modularity-folder-browser'),
                    'source' => __('Source', 'modularity-folder-browser'),
                    'selectedCount' => __('selected', 'modularity-folder-browser'),
                    'apply' => __('OK', 'modularity-folder-browser'),
                    'cancel' => __('Cancel', 'modularity-folder-browser'),
                    'expand' => __('Expand folder', 'modularity-folder-browser'),
                    'collapse' => __('Collapse folder', 'modularity-folder-browser'),
                    'root' => __('Source root', 'modularity-folder-browser'),
                    'selectedFolder' => __('Selected folder', 'modularity-folder-browser'),
                    'loading' => __('Loading folders.', 'modularity-folder-browser'),
                    'empty' => __('No folders found.', 'modularity-folder-browser'),
                    'error' => __('Folders could not be loaded.', 'modularity-folder-browser'),
                ),
            ));
        }
    }

    /**
     * Register the module
     * @return void
     */
    public function registerModule()
    {
        if (function_exists('modularity_register_module')) {
            modularity_register_module(
                MODULARITY_FOLDER_BROWSER_MODULE_PATH,
                'FileBrowser'
            );
        }
    }
}
