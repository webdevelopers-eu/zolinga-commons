# Message Board

This widget manages a popup messages.

## Usage

First you need to add a widget to the page.
```html
<message-board></message-board>
```

Then you can broadcast a message with name `message` to show a message. As usual web components
extending [WebComponent class](:Zolinga Core:Web Components:WebComponent Class) can use `this.broadcast()` and other code may use [Api](:Zolinga Core:Running the System:AJAX)'s `api.broadcast()` method.

```javascript 
this.broadcast('message', { 
    message: 'Hello, World!',
    type: 'info',
    timeout: 5000
    id: 'message-unique-id'
});
``` 

## Parameters

The message payload object may contain the following properties:

- `message` - the message to display
- `type` - the message type. Can be `info`, `warning`, `error`, `success`
- `timeout` - the time in milliseconds to display the message
- `id` - the unique message identifier. If not provided, the message will be displayed as a new message. If provided, the existing message will be replaced with the new one.