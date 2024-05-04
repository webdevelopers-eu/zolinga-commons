# C-Resources

Include common resources in your page.

## Usage

```html
<c-resources assets="{ASSETS}"></c-resources>
```

- `assets`: list of white-space separated asset ids to include in the page.
  
# Supported Assets

The full list of supported assets is compiled dynamically and stored in `/data/zolinga-commons/resources.json` .

- `web-components`: include support for web components. Note that without this asset, web components will not work on the front-end including the `<c-resources>`. Specifying this asset makes sense only for server-side parsed content as `<c-resources>` tag is also server-side parsed. On front-end the support for [Web Components](:Zolinga Core:Web Components) must be already present in order for `<c-resources>` to work so this asset is not needed.
- `forms/css`: include standardized CSS for forms.
