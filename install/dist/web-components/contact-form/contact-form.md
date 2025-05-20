<!-- filepath: /var/www/v2.ipdefender.eu/modules/zolinga-commons/install/dist/web-components/contact-form/contact-form.md -->
# Contact Form Web Component

`<contact-form>` is a reusable web component that provides an interactive contact form for collecting user submissions and sending them via email.

## Usage

To use the contact form component in your page, include the following HTML:

```html
<contact-form>
  <form>
    <!-- Your form fields here -->
    <div class="form-group">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required>
    </div>
    
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required>
    </div>
    
    <div class="form-group">
      <label for="message">Message</label>
      <textarea id="message" name="message" required></textarea>
    </div>
    
    <button type="submit">Submit</button>
  </form>
  
  <thank-you hidden aria-hidden="true">
    <h3>Thank you for your message!</h3>
    <p>We will get back to you as soon as possible.</p>
  </thank-you>
</contact-form>
```

## Required Elements

The component requires two child elements:

1. **`<form>`** - Contains the input fields for user data collection
2. **`<thank-you>`** - Displays a thank you message after successful submission

Both elements are required for the component to function properly. The component will log an error to the console if either element is missing.

## Form Fields

You can include **any** HTML form fields in the `<form>` element - the component uses `FormData` and `Object.fromEntries()` to collect all inputs regardless of their type or name. All form fields will be:
- Automatically collected and sent to the server on submission using their name attributes as keys
- Included in the email notification with their respective values

The component has no restrictions on field types or names. You can include:
- Text inputs (`<input type="text">`)
- Email inputs (`<input type="email">`)
- Textareas (`<textarea>`)
- Checkboxes (`<input type="checkbox">`)
- Radio buttons (`<input type="radio">`)
- Select dropdowns (`<select>`)
- File uploads (`<input type="file">`)
- Hidden fields (`<input type="hidden">`)
- Custom inputs
- Or any other valid HTML form element

## Form Submission

When the form is submitted:

1. The form data is collected and sent to the server
2. The form is temporarily disabled to prevent multiple submissions
3. Upon successful submission:
   - The form is hidden
   - The thank you message is displayed
   - After few seconds, the form resets and becomes visible again
4. If an error occurs during submission:
   - An error message is displayed
   - The form remains editable for resubmission

## Server Configuration

The contact form requires proper configuration of the contact email address in your system configuration:

```json
{
  "contact": {
    "email": "contact@{hostname}"
  }
}
```

The `{hostname}` placeholder will be replaced with the actual domain of the website.

## Customization

You can style the component using CSS. The component itself does not provide any default styling, allowing you to match it to your site's design.

```css
contact-form {
  /* Your custom styles here */
}

contact-form form {
  /* Form styles */
}

contact-form thank-you {
  /* Thank you message styles */
}
```
