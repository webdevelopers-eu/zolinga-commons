# Tag List Editor

This web component allows you to edit and remove multiple [<tag-editor>](:ref:wc:tag-editor) widgets.

# Syntax

```html
<tag-list-editor 
    [name="{NAME}"]
    [type="{TYPE}"] 
    [validation-error="{ERROR_MESSAGE}"]
    [max-tags="{MAX}"]
    [pattern="{PATTERN}"]
    [minlength="{MIN_LENGTH}"]
    [maxlength="{MAX_LENGTH}"]
    [min="{MIN_NUMBER}"]
    [max="{MAX_NUMBER}"]
    [step="{STEP}"]
    [readonly]
    [no-edit]
    [no-remove]
    >{...TAGS}</tag-list-editor>
```

## Attributes

- `{NAME}` - the name of the tag list - will be propagated to all nested `<tag-editor>` elements.
- `{MAX}` - the maximum number of tags that can be added to the list. If the limit is reached the "Add" button will be disabled.
- `readonly` - if present the tag list will be displayed as a read-only list without the ability to edit or remove the tags. Same as setting `no-edit` and `no-remove` at the same time.
- `no-edit` - if present the tag list will be displayed as a read-only list with the remove icon but without the ability to edit the tags.
- `no-remove` - if present the tag list will be displayed as an editable list without the remove icon.

## Properties

- `tags: string[]` - the list of tags in the editor. This is a read-write property that can be set even before the component is defined. Example: `document.querySelector('tag-list-editor').tags = ['Tag 1', 'Tag 2', 'Tag 3']`.

## Methods

- `addTag(tag: string)` - adds a new tag to the list. Example: `document.querySelector('tag-list-editor').addTag('My Tag')`.
- `setTags(tags: string[])` - sets the tags in the list. Example: `document.querySelector('tag-list-editor').setTags(['Tag 1', 'Tag 2', 'Tag 3'])`.
- `reset()` - removes all tags from the list.

## Example

```html
<form>
  <tag-list-editor name="tags[]" max="5">
    <tag-editor>tag1</tag-editor>
    <tag-editor>tag2</tag-editor>
    <tag-editor>tag3</tag-editor>
  </tag-list-editor>
</form>
