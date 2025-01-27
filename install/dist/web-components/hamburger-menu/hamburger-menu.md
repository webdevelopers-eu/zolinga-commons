# `<hamburger-menu>`

The `<hamburger-menu>` web component is a custom element that provides a responsive hamburger menu. It automatically detects overflow and toggles between a full-width menu and a collapsed hamburger menu.

## Features

- Automatic detection of overflow to toggle the hamburger menu.
- Customizable styles through CSS.
- Supports special classes for active and inactive states.

## Usage

### HTML

To use the `<hamburger-menu>` component, include it in your HTML file:

```html
<script type="module" src="/dist/system/js/web-components.js"></script>
<hamburger-menu>
    <nav>
        <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
    </nav>
</hamburger-menu>
```

### CSS Classes

The `<hamburger-menu>` component uses several CSS classes to manage its appearance and behavior:

- `.hamburger-menu`: Base class for the component.
- `.hamburger-ready`: Applied when the component is ready to be used.
- `.hamburger-active`: Applied when the menu is collapsed into a hamburger menu due to overflow.
- `.hamburger-open`: Applied when the hamburger menu is collapsed (has `.hamburger-active` class) and pop-up menu is displayed.