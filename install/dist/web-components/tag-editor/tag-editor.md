# Tag Editor

This web component allows you to edit and remove one tag. By default it is displayed as an editable pill-like element with "remove" icon next to it.

The editor will automatically create hidden `<input>` element with the same name as the tag editor and will update its value when the tag is changed so when placed inside a form it will be submitted as a regular input.

## Usage

```html
<tag-editor 
    [name="{NAME}"] 
    [value="{VALUE}"]
    [type="{TYPE}"]
    [validation-error="{ERROR_MESSAGE}"]
    [pattern="{PATTERN}"]
    [minlength="{MIN_LENGTH}"]
    [maxlength="{MAX_LENGTH}"]
    [min="{MIN_NUMBER}"]
    [max="{MAX_NUMBER}"]
    [step="{STEP}"]
    [readonly]
    [no-edit]
    [no-remove]
    [autofocus]
    ></tag-editor>
```

### Properties

- `{VALUE}` - the initial value of the tag - can be specified as an attribute or as a text content of the tag
- `{NAME}` - the name of the tag - will be used as the name of the hidden input element.
- `readonly` - if present the tag will be displayed as a read-only pill without the remove icon and ability to edit or remove the tag. Same as setting `no-edit` and `no-remove` at the same time.
- `no-edit` - if present the tag will be displayed as a read-only pill with the remove icon but without the ability to edit the tag.
- `no-remove` - if present the tag will be displayed as an editable pill without the remove icon.
- `autofocus` - place the cursor in the input field when the page is loaded.
- `{PATTERN}` - a regular expression pattern that the tag must match. If the tag does not match the pattern it will be displayed as invalid and the form will not be submitted.

## Example

```html
<form>
  <tag-editor name="tags[]">tag1</tag-editor>
  <tag-editor name="tags[]">tag2</tag-editor>
  <tag-editor name="tags[]">tag3</tag-editor>
</form>
```

## Methods and Properties

- `element.value` - set/get the value of the tag
- `element.name` - set/get the name of the tag
- `element.focus()` - focus the tag editor

