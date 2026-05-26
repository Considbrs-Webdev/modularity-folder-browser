<?php

/**
 * Plugin Name:       Modularity Folder Browser
 * Plugin URI:        https://github.com/considbrs-webdev/modularity-folder-browser
 * Description:       A Folder Browser module for Modularity.
 * Version: 1.0.0
 * Author:            Consid Borås AB
 * Author URI:        https://github.com/considbrs-webdev
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       modularity-folder-browser
 * Domain Path:       /languages
 */

// Protect against direct file access
if (!defined('WPINC')) {
    die;
}

define('MODULARITY_FOLDER_BROWSER_PATH', plugin_dir_path(__FILE__));
define('MODULARITY_FOLDER_BROWSER_URL', plugins_url('', __FILE__));
define('MODULARITY_FOLDER_BROWSER_VIEW_PATH', MODULARITY_FOLDER_BROWSER_PATH . 'views/');
define('MODULARITY_FOLDER_BROWSER_MODULE_VIEW_PATH', plugin_dir_path(__FILE__) . 'source/php/Module/views');
define('MODULARITY_FOLDER_BROWSER_MODULE_PATH', MODULARITY_FOLDER_BROWSER_PATH . 'source/php/Module/');
    
add_action('acf/init', function() {
    load_plugin_textdomain('modularity-folder-browser', false, plugin_basename(dirname(__FILE__)) . '/languages');
}); 

// Autoload from plugin
if (file_exists(MODULARITY_FOLDER_BROWSER_PATH . 'vendor/autoload.php')) {
    require_once MODULARITY_FOLDER_BROWSER_PATH . 'vendor/autoload.php';
}
require_once MODULARITY_FOLDER_BROWSER_PATH . 'Public.php';

// Acf auto import and export
add_action('acf/init', function () {
    $acfExportManager = new \AcfExportManager\AcfExportManager();
    $acfExportManager->setTextdomain('modularity-folder-browser');
    $acfExportManager->setExportFolder(MODULARITY_FOLDER_BROWSER_PATH . 'source/php/AcfFields/');
    $acfExportManager->autoExport(array(
        'instance-settings' => 'group_modularity_folder_browser_instance',
    ));
    $acfExportManager->import();
}); 

// Modularity 3.0 ready - ViewPath for Component library
add_filter('/Modularity/externalViewPath', function ($arr) {
    $arr['mod-folder-browser'] = MODULARITY_FOLDER_BROWSER_MODULE_VIEW_PATH;
    return $arr;
}, 10, 3);

// Start application
new ModularityFolderBrowser\App();
