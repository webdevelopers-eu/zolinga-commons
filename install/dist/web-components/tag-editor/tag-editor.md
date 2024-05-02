# Tag Editor

This web component allows you to edit and remove one tag. By default it is displayed as an editable pill-like element with "remove" icon next to it.

The editor will automatically create hidden `<input>` element with the same name as the tag editor and will update its value when the tag is changed so when placed inside a form it will be submitted as a regular input.

## Usage

```html
<tag-editor 
    [name="{NAME}"] 
    [value="{VALUE}"] 
    [readonly]
    [no-edit]
    [no-remove]
    >{VALUE}</tag-editor>
```

### Properties

- `{VALUE}` - the initial value of the tag - can be specified as an attribute or as a text content of the tag
- `{NAME}` - the name of the tag - will be used as the name of the hidden input element.
- `readonly` - if present the tag will be displayed as a read-only pill without the remove icon and ability to edit or remove the tag. Same as setting `no-edit` and `no-remove` at the same time.
- `no-edit` - if present the tag will be displayed as a read-only pill with the remove icon but without the ability to edit the tag.
- `no-remove` - if present the tag will be displayed as an editable pill without the remove icon.