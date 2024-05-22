# Popup Container

The `popup-container` component creates a popup layer with a default layout.


## Syntax

```html
<popup-container [width="{WIDTH}"]>
  <div slot="title|content|actions|nav-back|nav-menu">...</div>+
</popup-container>
```

## Usage

```html
<popup-container width="480px">
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


## Events

The widget fires following DOM events:

- `popup-close`: Fired when the popup is closed.
- `popup-open`: Fired when the popup is opened.
- `popup-remove`: Fired when the popup is removed.

## Methods
- `open()`: Opens the popup.
- `close()`: Closes the popup.
- `remove()`: Removes the popup from the DOM.
- `cover()`: Covers the popup with a transparent layer. This is useful when you want to prevent the user from interacting with the page behind the popup. In essence it adds `cover` class to the popup.
- `uncover()`: Removes the cover layer from the popup. This is useful when you want to allow the user to interact with the page behind the popup. In essence it removes `cover` class from the popup.
