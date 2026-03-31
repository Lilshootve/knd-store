<?php
/**
 * KND Labs - Model Viewer data
 *
 * Central place to register 3D models that can be previewed and downloaded
 * by the Model Viewer tool. This keeps markup lean and makes it easy to add
 * more entries later.
 */

if (!function_exists('knd_get_model_viewer_items')) {
    /**
     * @return array<int, array<string, string>>
     *
     * Each item:
     * - title
     * - description
     * - viewer_glb_url
     * - download_zip_url
     * - thumbnail_url (optional)
     */
    function knd_get_model_viewer_items(): array
    {
        return [
            [
                'title'           => 'Cyber Samurai',
                'description'     => '3D preview and downloadable package.',
                'viewer_glb_url'  => '/assets/labs/models/cyber-samurai/model.glb',
                'download_zip_url'=> '/assets/labs/models/cyber-samurai/cyber-samurai.zip',
                'thumbnail_url'   => '/assets/labs/models/cyber-samurai/preview.webp',
            ],
            // Add more models here following the same structure.
        ];
    }
}

