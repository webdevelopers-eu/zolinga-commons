# ticker-slider

Closed, style-isolated horizontal ticker. Duplicates its slotted children to enable seamless infinite scrolling.

## Usage

Place any inline content as children; the component will loop them horizontally.

```html
<ticker-slider style="--ticker-speed: 20s; --ticker-gap: 1.5rem;">
  <span>Item A</span>
  <span>Item B</span>
  <span>Item C</span>
  <span>Item D</span>
  <!-- more items... -->
  <!-- You can include other web components here as well. -->
  <!-- The component clones content internally for seamless looping. -->
  <!-- Use CSS variables on the host to tweak behavior. -->
  <!-- Animation pauses on :hover. -->
  <!-- Shadow root is "closed" to avoid inheriting external CSS. -->
  <!-- Provide your own styling inside items if needed. -->
  <!-- No scripts inside content are executed. -->
  <!-- For accessibility, ensure items have sufficient contrast and spacing. -->
  <!-- If items are interactive, consider disabling the pause-on-hover via CSS override. -->
  <!-- Example: :host(:hover) .track { animation-play-state: running; } -->
  <!-- You can also set width/height to fit your layout. -->
  <!-- Default gap and speed can be overridden using CSS variables. -->
  <!-- See variables below. -->
  slot
</ticker-slider>
```

## Customize

- --ticker-gap: space between items (default 2rem)
- --ticker-speed: time to complete one loop (default 30s)

Tip: Keep item widths varied for a natural ticker feel. For advanced effects, override the animation and provide your own CSS.
