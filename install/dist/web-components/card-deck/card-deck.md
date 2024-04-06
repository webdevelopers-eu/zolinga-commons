## Overview

The `card-deck` component is a container that holds elements styled as flip cards. It is a WHATWG Web Component that allows you to create a deck of flip cards.

Each direct child element of the `card-deck` component must have a unique `card-name` attribute within the `card-deck` component. This attribute is used to identify the flip card when it is flipped.

The `show-card` attribute on the `card-deck` is used to show a specific flip card. The value of the `show` attribute should be the value of the `card-name` attribute of the flip card that should be shown.

## Usage

To use the `card-deck` component, you need to include it in your HTML file and define the flip cards as its direct child elements. Here's an example:

```html
<style>
    card-deck > *[card-name] {
        min-width: 160px;
        background: #f0f0f0;
    }
</style>
<card-deck append-to="banner"  flip-speed="1" show-card="card-a" flip-perspective="1000">
    <div card-name="card-a" class="o0" style="height: 300px;"
            onclick="this.parentNode.showCard('card-b');">aaa</div>
    <div card-name="card-b" class="o1" style="height: 300px;" 
        onclick="this.parentNode.setAttribute('show-card', 'card-c');">bbb</div>
    <div card-name="card-c" class="o2" style="height: 150px;"
            onclick="this.parentNode.showCard('card-a');">cccc</div>
</card-deck>
```
