## Description

Content element handler for the `<c-resources>` custom HTML tag. Injects CSS and JS asset references into the page.

- **Event:** `cms:content:c-resources`
- **Class:** `Zolinga\Commons\Resources\ResourcesElement`
- **Method:** `onResources`
- **Origin:** `internal`
- **Event Type:** `\Zolinga\Cms\Events\ContentElementEvent`

## Usage

```html
<c-resources assets="module-name/style.css,module-name/script.js"></c-resources>
```

## Attributes

| Attribute | Type | Description |
|---|---|---|
| `assets` | `string` | Comma-separated list of asset paths (CSS/JS) to include |

## Behavior

Resolves asset paths and injects appropriate `<link>` or `<script>` tags into the DOM.
