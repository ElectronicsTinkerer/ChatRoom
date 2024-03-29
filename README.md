# ChatRoom (Messenger)

A PHP-based simple chatroom/messenger web "app"

## About

This is a small messaging system that I designed so I could stop emailing myself links (it's not like there are any *actual* services that could do that for me ;)\
It runs off of three files:

- `messenger.php` - The code that makes everything work
- `messenger.json` - The file in which the messages are stored
- `messenger-style.css` - The stylesheet (Makes things look somewhat nice)

This is pretty much my first PHP project, so if it seems messy or oddly constructed, it probably is (but I'm working on it).\
A couple of notes about the system:

- It is known to work on PHP 7.3.18 and 7.3.19
- The webserver needs permission to read and write to the `messenger.json` file.

Also, this could be combined with a login page to add a level of security if you are using it for private messages.

## Features

The messenger supports several commands/features in addition to just being able to send messages:

- `#` - Messages can be tagged with as many `#`s as you would like. They must be setup like hashtags on many popular social media sites where the `#` is prepended to the tag. Unlike social media (as far as I am aware), this will remove the tags if they are at the beginning of the message but retain any that are part of a message (i.e. a tag that is begun after a non-whitespace block that does not begin with the `#`). E.g. `#tag1 #tag2 My message #inlineTag1 here!` would post: `My message #inlineTag1 here!` with the tags `#tag1` `#tag2` `#inlineTag1`
- `!` - Search for a message: case-insensitive, partial or full tags are allowed.
- Also on the topic of searching, you can click on a message's tags to preform a search for all related tags.
- Messages can also be deleted by hovering over them and clicking the `Delete` button. There is a deletion-confirmation message, so if you accidently click the button, don't be too worried.
- A message's text can be copied to the clipboard simply by clicking on the text of the message.
- Messages can be `Ctrl + Click`ed to be opened in another tab as a link. (Will copy text to clipboard as well)
- When a new message appears, a notification will popup that can be clicked to scroll to the bottom (it will disappear in 5 seconds if not clicked and can be disabled in the Options menu). This way, the user is not interrupted by a new message and can choose to see it immediately or continue reading further up in the messages.
- If you use a web browser that allows for setting of custom search engines, you can set a custom "search engine" as the messenger. This lets you easily post messages without having to first load the page. Just make sure that the browser sends a `POST` request with the key `message` set. For example, on Chrome just set `https://[some URL stuff]/messenger.php?message=%s` as a search engine and you should be good to go! (As of 2020-01-16, if you are not signed into the messenger, your message will be saved temporarily that you can sign in to send the message.).
- The user has some Options (auto-scrolling and auto-message-box focus) that can be accessed by clicking on the "Welcome" message.
- Markdown is now available! Available options are:
    * `--Strikethrough--`
    * `__Underscore__`
    * `**Bold**`
    * `*Italic*`
    * ``` `Code` ```
- You can now @Username people (to highlight them)
