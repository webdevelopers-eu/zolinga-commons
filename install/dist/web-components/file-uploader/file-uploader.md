# File Uploader

Simple drag&drop upload component for uploading multiple files.

## Usage

```html
<file-uploader
    [name="INPUT_FIELD_NAME"]
    [accept="ACCEPT_MIME_TYPES"]
>
    <template>Z_TEMPLATE</template>
</file-uploader>
```


Example:
```html
<file-uploader accept="image/*" name="file">
    <template>
        <div class="fu-file">
            <input type="hidden" z-var="fieldName @name, uri =" />
            <img role="remove-file-confirm" class="fu-object" title="${name} (${sizeHR})" z-var="name @title, sizeHR @title, url @src, type @type" />
            <div class="fu-file-name" z-var="name .">Name</div>
            <div class="fu-file-size" z-var="size .">Size</div>
            <div class="fu-file-last-modified" z-var="lastModified .">Modified</div>
            <div class="fu-file-remove" role="remove-file-confirm">✖</div>
        </div>
    </template>
</file-uploader>
```


## Template

For each uploaded file the `template` is cloned and appended to the widget element itself. The [Z-Template](https://github.com/webdevelopers-eu/z-template) array is applied to the template element. The following variables are available:

- `uri` - The Uploader URI of the uploaded file. Use `$api->uploader` service to get the file content.
- `fieldName` - The name of the file input field.
- `name` - The name of the file.
- `size` - The size of the file in bytes.
- `sizeHR` - The size of the file in human readable format.
- `type` - The MIME type of the file.
- `lastModified` - The last modified date of the file.
- `url` - The URL of the file. This is a temporary URL and should not be used for long term storage. It is meant only for preview.

## Methods 

- `addFile({name, size, type, lastModified, url, uri})` - Adds a file to the uploader. The file object should have the same properties as the template variables. File should already be uploaded. This method will only display new file in the list without uploading it.
- `removeFile(uri)` - Removes a file from the uploader. The file is removed from the list and the uploader is notified to remove the file from the server's upload queue.
- `waitForUpload()` - Returns a promise that resolves when all files are uploaded. This is useful when you want to wait for all files to be uploaded before submitting the form.

## Usage Example

```javascript
const uploader = document.querySelector('file-uploader');
await this.waitForComponent(uploader);
uploader.add({
    "uri": "db:1234",
    "url": "/dist/img/view?uri=db:1234",
    "sizeHR": "120 KB",
    "type": "image/jpeg",
});
```