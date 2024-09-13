# ModxOptimyPlugin
The plugin for CMF MODX Revolution:
* minifies html code
* replaces paths of **jpg**, **jpeg** and **png** images to "webp" format in:
* * "src" attribute of tags "img"
* * "background" and "background-images" props of "style" attribute of all tags 
* * "data-" attributes of all tags (optionally)
* converts images using ajax for a quick response from the server
* does next actions for **jpg**, **jpeg** and **png** images in the admin panel:
* * create a **webp** copy when the original file is loaded
* * renames a **webp** copy when the original file is renamed
* * deletes a **webp** copy when the original file is deleted

## Installation
Create a plugin `ModxOptimyPlugin` in the admin panel and hang it on the events:
* `OnFileManagerUpload`
* `OnFileManagerFileRemove`
* `OnFileManagerFileRename`
* `OnBeforeSaveWebPageCache`
* `OnWebPagePrerender`

If you want converts images using ajax, you should:
1. copy the **ModxOptimyPlugin.php** file from the repository and put it in the directory you need
2. check on the `Is Static` checkbox and browse **ModxOptimyPlugin.php** file in the `Static File` field

## Configuration
You can enable/disable the features you need in the variable `$globals['settings']` of the **ModxOptimyPlugin.php** file. There are comments about it.

## Notes
1. Copies will have the suffix ".webp" appended to them (ex. "/assets/image.jpg.webp").
2. Replacing using **DOM** will be added in the future.