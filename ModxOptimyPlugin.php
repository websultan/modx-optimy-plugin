<?php
/**
 * Name: ModxOptimyPlugin
 * Author: WebSultan
 * Description: Modx revo plugin for page optimization
 * Github Link: https://github.com/websultan/modx-optimy-plugin
 */

$globals = [
    'settings' => [
        // comment out this line or set 'false' if shouldn't to minify html code
        'shouldMinify' => true,
        // comment out this line or set 'false' if shouldn't to replace images to webp
        'shouldReplaceImages' => true,
        // comment out this line or set 'false' if shouldn't to convert images using ajax
        'shouldConvertUsingAjax' => true,
        // add 'data-' attributes for replacing images to webp (ex. - ['src', 'background'])
        // don't need to add prefix 'data-' to these attributes
        // if tag has many attributes then image will be replace path in a first attribute
        'attributesForReplacing' => ['src'],
        'quality' => [
            'jpg' => 90,
            'jpeg' => 90,
            'png' => 100,
        ],
    ]
];

/*** DON'T EDIT ALL CODE BELOW! ***/

// Initialization $modx for ajax request
if (
    ! isset($modx)
    && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'
    && ! empty($_POST)
    && ! empty($_SERVER['DOCUMENT_ROOT'])
) {
    define('MODX_API_MODE', true);

    require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
    
    $modx->getService('error','error.modError');
    $modx->setLogLevel(modX::LOG_LEVEL_INFO);
    $modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');
}

// 404 for a simple request
if (! isset($modx) && empty($_POST) ) {
    header('HTTP/1.1 404 Not Found');
    
    return;
}

/* FUNCTIONS */
if (! function_exists('minifyHtml')) {
    function minifyHtml($content) {
        $content = preg_replace('/<!--\s*[^\[].*?(?<=--)>/s', '', $content);
        $content = preg_replace('/\s*\n\s*/', "\n", $content);
        $content = preg_replace('/[ \t]{2,}/', ' ', $content);

        return $content;
    }
}

if (! function_exists('replaceImages')) {
    function replaceImages($globals, $content) {
        $imagesOriginal = [];
        $imagesWebp = [];
        $attributesForReplacing = $globals['settings']['attributesForReplacing'];
        $attrsForPattern = (! empty($attributesForReplacing)) ? implode('|', $attributesForReplacing) : '';
        
        $patterns = [
            '/(<img[\s|"]*[^>]*[\s|"]src\s*=\s*")([^>"]+\.(?:jpe?g|png))(\??[a-z0-9=_-]*?"[^>]*>)/iu',
            '/(background(?:-image)?\s*:\s*url\(\'?)([^\'\"]+\.(?:jpe?g|png))(\??[a-z0-9=_-]*?\'?\s*\))/iu',
        ];

        if ($attrsForPattern) {
            $patterns[] = '/(\Wdata-(?:' . $attrsForPattern . ')\s*=\s*")([^>"]+\.(?:jpe?g|png))(\??[a-z0-9=_-]*?"[^>]*>)/iu';
        }

        foreach ($patterns as $pattern) {
            $content = preg_replace_callback(
                $pattern,
                function ($matches) use ($globals, $imagesOriginal, $imagesWebp
            ) {
                $imageUrl = $matches[2];
                $imageUrlProcessed = ltrim($imageUrl, '/');
            
                if ( ! in_array( $imageUrlProcessed, $imagesOriginal ) ) {
                    $imagesOriginal[] = $imageUrlProcessed;
                    $imageFullPath = $globals['basePath'] . $imageUrlProcessed;
            
                    if ( file_exists($imageFullPath) ) {
                        $imageWebpFullPath = $imageFullPath . $globals['nameEnding'];
                        
                        if ( file_exists($imageWebpFullPath) ) {
                            $imagesWebp[$imageUrlProcessed] = $imageUrl . $globals['nameEnding'];
                        } else {
                            if (
                                isset($globals['settings']['shouldConvertUsingAjax'])
                                && ! in_array($imageUrlProcessed, $globals['session']['images'])
                            ) {
                                $globals['session']['images'][] = $imageUrlProcessed;
                            }
                        }
                    } else {
                        return $matches[1] . $matches[2]. $matches[3];
                    }
                }
                
                if ( ! array_key_exists($imageUrlProcessed, $imagesWebp) ) {
                    return $matches[1] . $matches[2]. $matches[3];
                } else {
                    return $matches[1] . $imagesWebp[$imageUrlProcessed] . $matches[3];
                }
            }, $content);
        }

        return $content;
    }
}

if (! function_exists('convertToWebp')) {
    function convertToWebp($globals, $url) {
        $outFilePath = $globals['basePath'] . ltrim($url, '/');

        $outFile = pathinfo($outFilePath);
        $ext = $outFile['extension'];

        if ( ! in_array($ext, ['jpg', 'jpeg', 'png']) ) {
            return false;
        }

        if ( convertUsingGD($globals, $outFilePath, $ext) ) {
            return true;
        }

        return false;
    }
}

if (! function_exists('convertUsingGD')) {
    function convertUsingGD($globals, $file, $ext) {
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $image = imageCreateFromJpeg($file);
        } elseif ($ext === 'png') {
            $image = imageCreateFromPng($file);
        }

        if (! $image) {
            return false;
        }

        $results = [];

        $results[] = imagepalettetotruecolor($image);
        $results[] = imagealphablending($image, true);
        
        if ($ext === 'png') {
            $results[] = imagesavealpha($image, true);
        }

        $results[] = imagewebp($image, $file . $globals['nameEnding'], $globals['settings']['quality'][$ext]);
        $results[] = imagedestroy($image);

        if ( in_array(false, $results) ) {
            return false;
        }

        return true;
    }
}

if (! function_exists('sendAjaxQuery')) {
    function sendAjaxQuery($globals, $content) {
        $isStatic = $globals['modxPlugin']->static;
        $staticFile = '/' . ltrim($globals['modxPlugin']->static_file, '/');

        if ($isStatic) {
            $script = 
            '<' . 'script>
                document.addEventListener("DOMContentLoaded", function(){
                    var formData = new FormData();
                    formData.append("action", "' . $globals['ajaxActionName'] . '");

                    try {
                        fetch("' . $staticFile . '", {
                            method: "POST",
                            body: formData,
                            headers: {
                                "X-Requested-With": "XMLHttpRequest"
                            }
                        })
                        .then(response => response.json())
                        .then(response => {
                            if (response.status == "1") {
                                console.log(response.status);
                                console.log(response.message);

                                return true;
                            } else {
                                console.log("Failed to convert images!");

                                return false;
                            }
                        })
                        .catch((e) => {
                            console.log("Plugin request error: " + e.message);
                        });
                    } catch (error) {
                        console.log("JS error: " + error.message);
                    }
                });
            <' . '/script>';

            $content = preg_replace('#(</body>)#i', $script . '$1', $content);
        }

        return $content;
    }
}

if (! function_exists('convertUsingAjax')) {
    function convertUsingAjax($globals) {
        $images = $globals['session']['images'];

        if ( empty($images) ) {
            return false;
        }

        for ($i = 0; $i < count($images); $i++) {
            $image = $images[$i];
            $imageFullFileName = $globals['basePath'] . ltrim($image, '/');
            $imageWebpFullFileName = $imageFullFileName . $globals['nameEnding'];

            if ( file_exists($imageFullFileName) && ! file_exists($imageWebpFullFileName) ) {
                if ( convertToWebp($globals, $image) ) {
                    unset($globals['session']['images'][$i]);
                }
            }
        }
        
        return true;
    }
}
/* /FUNCTIONS */

$globals['nameEnding'] = '.webp';

/* For ajax query */
$globals['pluginName'] = 'ModxOptimyPlugin';
$globals['ajaxActionName'] = $globals['pluginName'] . '_action_convert';
$globals['basePath'] = rtrim( str_replace( '\\', '/', $modx->config['base_path'] ), '/' ) . '/';

$globals['session'] = &$_SESSION[$globals['pluginName']];

if (! isset($globals['session'])) {
    $globals['session'] = [
        'images' => [],
    ];
}

if (
    $_POST['action'] === $globals['ajaxActionName'] && $globals['session']['images']
    && strripos( $_SERVER['HTTP_REFERER'], $modx->config['site_url'] ) === 0
) {
    if ( convertUsingAjax($globals) ) {
        echo json_encode( ['status' => 1, 'message' => 'Converting images to webp was successful'] );
    }

    exit;
}
/* /For ajax query */

$modxEventName = $modx->event->name;
$globals['modxPlugin'] = $modx->event->plugin;
$globals['isEnabledCacheResources'] = $modx->getOption('cache_resource');
$globals['isResourceCacheable'] = $modx->resource->_cacheable;
$globals['shouldCacheResource'] = false;

if ($globals['isEnabledCacheResources'] && $globals['isResourceCacheable'] !== '0') {
    $globals['shouldCacheResource'] = true;
}

/* EVENTS */
if ($modxEventName === 'OnFileManagerUpload' && in_array($files['file']['type'], ['image/png', 'image/jpeg'])) {
    convertToWebp($globals, $directory . $files['file']['name']);
}

if ($modxEventName === 'OnFileManagerFileRemove') {
    $webpFile = $path . $globals['nameEnding'];

    if ( file_exists($webpFile) ) {
        unlink($webpFile);
    }
}

if ($modxEventName === 'OnFileManagerFileRename') {
    $webpFile = $globals['basePath'] . $_POST['path'] . $globals['nameEnding'];

    if ( file_exists($webpFile) ) {
        rename($webpFile, $path . $globals['nameEnding']);
    }
}

if ($modxEventName === 'OnBeforeSaveWebPageCache' && $globals['shouldCacheResource']) {
    $content = &$modx->resource->_content;
    
    $content = minifyHtml($content);
}

/* Event for replacing of images on webp */
if ($modxEventName === 'OnWebPagePrerender') {
    $content = &$modx->resource->_output;
    
    if ($globals['settings']['shouldMinify'] && ! $globals['shouldCacheResource']) {
        $content = minifyHtml($content);
    }
    
    if (
        $globals['settings']['shouldReplaceImages']
        && stripos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false
        && $modx->resource->get('contentType') == 'text/html'
    ) {
        $content = replaceImages($globals, $content);
    }

    if ($globals['settings']['shouldConvertUsingAjax'] && ! empty($globals['session']['images'])) {
        $content = sendAjaxQuery($globals, $content);
    }
}
/* /EVENTS */