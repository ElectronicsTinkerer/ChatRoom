# ChatRoom
A PHP-based simple chatroom/messenger web "app"

## Working Example
There is a working example [here](https://zachspi.ddns.net/projects/chatroom/messenger.php), (hosted off a Raspberry Pi 4B).

## About
This is a small messaging system that I designed so I could stop emailing myself links (it's not like there are any actual services that could do that for me ;)
It runs off of two files:
- `messenger.php` - The code that makes everything work
- `messenger.json` - The file inwhich the messages are stored

This is pretty much my first PHP project, so if it seems messy or oddly constructed, it probablly is.
A couple of notes about the system:
- It is known to work on PHP 7.3.18 and 7.3.19
- The webserver needs permission to read and write to the `messenger.json` file
- If you get an `Invalid argument supplied in foreach()` make sure that `messenger.json` has an array inside. (If not, just add `{}` to the file)
- There is no file locking on `messenger.json` meaning that, given the right timing, the system could **delete** your messages, so keep that in mind.
- All time is computed server-side, with no current support for changing the timezone. (So if the post time seems wrong, that is probably why)

Also, this could be combined with a login page to add a level of security if you are using it for private messages.

## Features
The messenger supports several commands/features in addition to just being able to send messages:
- `#` - Messages can be tagged with as many `#`s as you would like. They must be setup like hashtags on many popular social media sites where the `#` is prepended to the tag. Unlike social media (as far as I am aware), this will remove the tags if they are at the beginning of the message but retain any that are part of a message (i.e. a tag that is begun after a non-whitespace block that does not begin with the `#`). E.g. `#tag1 #tag2 My message #inlineTag1 here!` would post: `My message #inlineTag1 here!`
- `!` - Search for a message: case-sensitive, exact matches only
- Messages can also be deleted by hovering over them and clicking the `Delete` button
- A message's text can be copied to the clipboard simply by clicking on the text of the message
- When a new message appears, a notification will popup that can be clicked to scroll to the bottom (it will disapear in 5 seconds if not clicked). This way, the user is not interrupted by a new message and can choose to see it immediately or continue reading further up in the messages.
