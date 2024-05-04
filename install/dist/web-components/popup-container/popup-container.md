# Popup Container

The `popup-container` component creates a popup layer with a default layout.


## Usage

```html
<popup-container>
  <div slot="title">This is the title</div>
  <div slot="content">
    <!-- Your content here -->
  </div>
</popup-container>
```

## Slots

- `title`: The title of the popup.
- `content`: The content of the popup.
- `actions`: The actions of the popup. Use it to add buttons or other actions to the popup.
- `nav-back`: Use it to add a back button or disable it to the popup.
- `nav-menu`: Place where a menu is usually placed.

