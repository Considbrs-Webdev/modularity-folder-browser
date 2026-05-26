<?php

use ComponentLibrary\Init as ComponentLibraryInit;

if (!function_exists('modularity_folder_browser_render_blade_view')) {
    function modularity_folder_browser_render_blade_view($view, $data = [], $compress = true)
    {
        $componentLibrary = new ComponentLibraryInit([]);
        $bladeEngine = $componentLibrary->getEngine();
        $data = array_merge($data, array('errorMessage' => false));
        $viewPath = MODULARITY_FOLDER_BROWSER_MODULE_VIEW_PATH;
        $markup = '';

        try {
            $markup = $bladeEngine->makeView($view, $data, [], $viewPath)->render();
        } catch (\Throwable $e) {
            $markup .= '<pre style="border: 3px solid #f00; padding: 10px;">';
            $markup .= '<strong>' . $e->getMessage() . '</strong>';
            $markup .= '<hr style="background: #000; outline: none; border:none; display: block; height: 1px;"/>';
            $markup .= $e->getTraceAsString();
            $markup .= '</pre>';
        }

        if ($compress == true) {
            $replacements = array(
              ["~<!--(.*?)-->~s", ""],
              ["/\r|\n/", ""],
              ["!\s+!", " "]
            );

            foreach ($replacements as $replacement) {
                $markup = preg_replace($replacement[0], $replacement[1], $markup);
            }

            return $markup;
        }

        return $markup;
    }
}

if (!function_exists('modularity_file_browser_get_sources')) {
    /**
     * Return configured filesystem sources that the module may expose.
     *
     * Sources must be provided by code, for example with the
     * MODULARITY_FILE_BROWSER_SOURCES constant or the
     * modularity_file_browser_sources filter.
     */
    function modularity_file_browser_get_sources(): array
    {
        $sources = defined('MODULARITY_FILE_BROWSER_SOURCES') && is_array(MODULARITY_FILE_BROWSER_SOURCES)
            ? MODULARITY_FILE_BROWSER_SOURCES
            : [];

        $sources = apply_filters('modularity_file_browser_sources', $sources);

        return is_array($sources) ? $sources : [];
    }
}
