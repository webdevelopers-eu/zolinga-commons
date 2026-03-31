## Syntax

```javascript
// Front-end (remote origin)
api.dispatchEvent('uploader', { op: 'remove', uri: 'private://uploads/file.jpg' });
```

## Description

File upload management API. Used by the `<file-uploader>` web component to manage uploaded files. The actual file upload is handled by the HTTP multipart mechanism; this event handles metadata operations like removal.

- **Event:** `uploader`
- **Class:** `Zolinga\Commons\Uploader\UploaderService`
- **Method:** `onUploader`
- **Origin:** `remote`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `op` | `string` | Operation: `remove` |
| `uri` | `string` | Zolinga URI of the file to remove |
