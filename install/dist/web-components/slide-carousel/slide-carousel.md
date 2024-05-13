# Slide Carousel

The Slide Carousel component is a carousel that displays a set of slides that can be scrolled through horizontally. User can drag the carousel to the left or right to view the next or previous slide. 

## Usage

```html
<slide-carousel [data-active="{NAME}"]>
    <div [data-name="{NAME}"]>...</div>
    ...
</slide-carousel>
```

Example:

```html
        <slide-carousel data-active="c4" style="height: 100px">
            <div data-name="c1">1 This is a test</div>
            <div data-name="c2">2 This is a test</div>
            <div data-name="c3">3 This is a test</div>
            <div data-name="c4">4 This is a test</div>
        </slide-carousel>
```

## Changing the Active Slide

To change the active slide, set the `data-active` attribute to the name of the slide you want to make active.

When user drags the carousel to the left or right, the `data-active` attribute will be updated with the white-space separated names of the slides that are currently visible in the carousel.

## Events

The Slide Carousel component emits the following events:

- `slide-carousel:change` - emitted when the active slide changes. The event detail contains the active slides.

```javascript
document.querySelector('slide-carousel:change').addEventListener('slide-carousel:change', (event) => {
    console.log(event.detail);
});
```

## Enabling Widgets

All elements with `disabled` attribute and with CSS class `auto-enable` will have the `disabled` attribute removed when the slide becomes active.

```html 
<slide-carousel data-active="c1">
    <my-listing disabled class="auto-enable"></my-listing>
    ...
</slide-carousel>
```

This allows auto-loading of widgets only when they are visible to the user.

For more information about Web Component's `disabled` attribute see [WebComponent](:Zolinga Core:Web Components:WebComponent Class).