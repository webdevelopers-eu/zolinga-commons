# Input Toggle

This widget displays a toggle switch that can be used to turn a setting on or off. Inside the toggle there is a native `<input type="checkbox">` control so it works as a normal checkbox.

## Syntax

```html
<input-toggle
    [name="{NAME}"]
    [checked]
    [readonly]
    [disabled]
    [required]
    [value="{VALUE}"]
    [form="{FORM_ID}"]
    [...]
    >
    [<input type="checkbox" ...>]
</input-toggle>
```

## Usage

```html
<input-toggle name="abc" checked></input-toggle>

<input-toggle>
    <input type="checkbox" name="abc" checked>
</input-toggle>
```
