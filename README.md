# ChatRoom

A PHP-based simple chatroom/messenger web "app"

## Working Example

There is a working example [here](https://zachspi.ddns.net/projects/chatroom/messenger.php), (hosted off a Raspberry Pi 4B).

## About

This is a small messaging system that I designed so I could stop emailing myself links (it's not like there are any *actual* services that could do that for me ;)\
It runs off of ~~two~~ three files:

- `messenger.php` - The code that makes everything work
- `messenger.json` - The file in which the messages are stored
- `messenger-style.css` - The stylesheet (okay, this isn't exactly *required* but it certainly helps)

This is pretty much my first PHP project, so if it seems messy or oddly constructed, it probably is (but I'm working on it).\
A couple of notes about the system:

- It is known to work on PHP 7.3.18 and 7.3.19
- The webserver needs permission to read and write to the `messenger.json` file.
- If you get an `Invalid argument supplied in foreach()` make sure that `messenger.json` has an array inside. (If not, just add `{}` to the file)
- There is no file locking on `messenger.json` meaning that, given the right timing, the system could **delete** your messages, so keep that in mind.
- All time is computed server-side, with no current support for changing the time zone. (So if the post time seems wrong, that is probably why)
- As of 2020-09-26, previous `messenger.json` files should have the leading `#` removed from the tags if importing from an older version. (It will display `##` instead of `#` on tags)

Also, this could be combined with a login page to add a level of security if you are using it for private messages.

## Features

The messenger supports several commands/features in addition to just being able to send messages:

- `#` - Messages can be tagged with as many `#`s as you would like. They must be setup like hashtags on many popular social media sites where the `#` is prepended to the tag. Unlike social media (as far as I am aware), this will remove the tags if they are at the beginning of the message but retain any that are part of a message (i.e. a tag that is begun after a non-whitespace block that does not begin with the `#`). E.g. `#tag1 #tag2 My message #inlineTag1 here!` would post: `My message #inlineTag1 here!` with the tags `#tag1` `#tag2` `#inlineTag1`
- `!` - Search for a message: case-insensitive, partial or full tags are allowed.
- Also on the topic of searching, you can click on a message's tags to preform a search for all related tags.
- Messages can also be deleted by hovering over them and clicking the `Delete` button. There is a deletion-confirmation message, so if you accidently click the button, don't be too worried.
- A message's text can be copied to the clipboard simply by clicking on the text of the message.
- Messages can be `Ctrl + Click`ed to be opened in another tab as a link. (Will copy text to clipboard as well)
- When a new message appears, a notification will popup that can be clicked to scroll to the bottom (it will disappear in 5 seconds if not clicked). This way, the user is not interrupted by a new message and can choose to see it immediately or continue reading further up in the messages.
- If you use a web browser that allows for setting of custom search engines, you can set a custom "search engine" as the messenger. This lets you easily post messages without having to first load the page. Just make sure that the browser sends a `POST` request with the key `message` set. For example, on Chrome: just set `https://[some URL stuff]/messenger.php?message=%s` as a search engine and you should be good to go! (As a word of caution, make sure that you are logged in when posting using this method, or you will lose your message).
