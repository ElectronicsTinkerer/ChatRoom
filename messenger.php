<?php
    const COOKIE_NAME = "device";
    const DEVICE_KEY  = "device";
    const MESSAGE_KEY = "message";
    const MESSAGE_ID_KEY = "ID";
    const TIME_KEY    = "time";
    const TAGS_KEY    = "tags";
    const MESSAGE_FILE = "messenger.json";

    // Debug
    $console_output = "";

    // Flags
    $message_filter = "";

    /**
     * Change a given string to be "safe" - This will strip whitespace from both ends,
     * remove backslashes, and convert characters to html (as per 'htmlspecialchars()')
     * 
     * @param $string The string to be 'filtered'
     * @return The input string converted to html chars, no backslashed, and no leading or trailing whitespace.
     */
    function cleanString($string) {
        $string = trim($string);
        $string = stripslashes($string);
        $string = htmlspecialchars($string);
        return $string;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" ) {
        if(isset($_POST['submit-button']) && !isset($_COOKIE[COOKIE_NAME])){ //check if form was submitted and add the user's cookie
            $cookie_value = cleanString($_POST['device']); //get input text
            setcookie(COOKIE_NAME, $cookie_value, time() + (86400 * 365), "/");
            header("refresh:0");
        } elseif (isset($_POST['logout'])) { // Delete the user's cookie (log them out)
            unset($_COOKIE[COOKIE_NAME]);
            setcookie(COOKIE_NAME, "", time() - 3600, '/');
            header("refresh:0");
        } elseif (isset($_POST['message']) && $_POST[MESSAGE_KEY] != "" && isset($_COOKIE[COOKIE_NAME])) { // Post message to server if not empty
            // Get the messages
            $messages = file_get_contents(MESSAGE_FILE);
            $messages_array = json_decode($messages, true);

            if (!is_array($messages_array)) {
                $messages_array = [];
            }

            // Make the message with a random, unique key
            $key = rand();
            while (in_array($key, $messages_array)) {
                $key = rand();
            }

            // Generate the tags array and strip leading tags
            $tag_identifier = "#";

            $message = trim($_POST[MESSAGE_KEY]);
            $tokens = preg_split("/[\s]+/", $message);  // Split by whitespace
            $tags = [];
            $removal_length = 0;
            $still_at_front = true;     // Indicates that the tags are still at the front of the string and the length should be counted for removal
            foreach ($tokens as $token) {

                if ($token != "") {     // Ignore double (or triple or quadruple or pentup--...) spaces
                    if ($token[0] == $tag_identifier) {
                        $tags[] = substr(cleanString($token), 1);
                        if ($still_at_front) {
                            $removal_length += strlen($token) + 1;    // Only remove the hashes from the front of the string, ones in the middle should be left alone
                        }
                    } 
                    else {
                        $still_at_front = false;    // No longer at front of message, stop counting tag length
                    }
                }
            }
            if ($removal_length != 0) {
                $message = substr($message, $removal_length - 1);   // -1 just in case there is no space after the last tag            
            }
            // $message = ltrim($message);
            

            if ($message != "") {   // Would not want a bunch of empty messages!
                // JSON data entry
                $message_data = [ $key => array(
                    TIME_KEY => time(),
                    DEVICE_KEY => cleanString($_COOKIE[COOKIE_NAME]),
                    MESSAGE_KEY => cleanString($message),
                    TAGS_KEY => $tags
                )];

                // Append the message
                $messages_array += $message_data;

                // Return to json and put updated to our file
                $messages_array = json_encode($messages_array, JSON_PRETTY_PRINT);
                file_put_contents(MESSAGE_FILE, $messages_array, LOCK_EX);
            }

            // If "js" is set, it (probably) means that the user POSTed a message via the page's built-in JS
            // Also, do not exit if the user searched something
            if (isset($_POST["js"])) {
                exit(0);  // Exit, do not display webpage 
            }

        } elseif (isset($_POST['delete-message'])) {
            // Get message
            $messages = file_get_contents(MESSAGE_FILE);
            $messages_array = json_decode($messages, true);

            // Delete the message
            unset($messages_array[cleanString($_POST['message-id'])]);

            // Return to json and put updated to our file
            $messages_array = json_encode($messages_array, JSON_PRETTY_PRINT);
            file_put_contents(MESSAGE_FILE, $messages_array, LOCK_EX);

            exit(0);  // Exit, do not display webpage
        } elseif (isset($_POST['update-time'])) { // If there is an update interval

            $time = cleanString($_POST['update-time']);

            $data = file_get_contents(MESSAGE_FILE);
            $data_array = json_decode($data, true);

            if (!is_array($data_array)) {
                $data_array = [];
            }

            $return_messages = [];

            // Print all the messages
            foreach ($data_array as $message_id => $message) {
                if ($message[TIME_KEY] > $time) {
                    $message += [ MESSAGE_ID_KEY => $message_id ];
                    array_push($return_messages, $message);
                } 
            }
            echo json_encode($return_messages);

            exit(0);  // Exit, do not display webpage
        }
        
        $header = "location: messenger.php";
        header($header);
    }

?>  
<!DOCTYPE html>
<html>
<head>
    <title>Zach's Messenger</title>
    <link rel="shortcut icon" href="favicon.ico"/>
    <link rel="stylesheet" href="messenger-style.css">
    <meta content="text/html;charset=utf-8" http-equiv="Content-Type">
    <meta content="utf-8" http-equiv="encoding">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<?php
    // Setup the cookies to know which device is sending the message
    if(!isset($_COOKIE[COOKIE_NAME])) { ?>
        <form class="modal-content" action="" method="post">
            <div class="container">
                <h1>Messenger Registration</h1>
                <p>Please fill in this form to create an account.</p>
                <hr>
                <label for="device"><b>Device</b></label>
                <input type="text" placeholder="Enter Device Name" name="device" autocomplete="off" required autofocus>
                <div class="clearfix">
                    <button name="submit-button" type="submit" class="signupbtn">Sign Up</button>
                </div>
            </div>
        </form>
    <?php } else { ?>  

        <!-- Display the "menu" bar -->
        <div class="navbar">
            <Strong>Messenger</strong>
            <i id="welcome-message" onclick="openSettings()">Welcome <?php echo cleanString($_COOKIE[COOKIE_NAME]) ?></i>
            <form action="" method="post">
                <button name="logout" id="logout-button">Logout</button>
            </form>
        </div>

        <!-- Chat container -->
        <div class="chat-container">

            <!-- "Loading" message -->
            <div id="loading-msg">
                Loading...
            </div>
    
            <!-- Message notification -->
            <div class="notification" id="message-notifier" onclick="scrollToBottom()">
                &dArr; New messages! &dArr;
            </div>

            <div id="chat-sub-container">
                <!-- Draw the messages -->
            </div>
            
            <div class='empty-message-container' id="first-person-message"style="display: none;">
                Looks like you're the first one here!<br>
                Type a message to get started.<br>
                You can tag messages with '#' at the beinning of keywords<br>
                and search by tags with a '!'.<br><br>
                (Make sure that searches are at the beginning of the message :)
            </div>

            <div class='empty-message-container' id="no-messages" style="display: none;">
                <span class='tags' id="no-messages-tag" style='margin: 10px;'></span><br>
                Looks like no one has posted with that tag!<br>
                Returning ... <span id='count-block'></span>
            </div>

            <div class='empty-message-container' id="yes-messages" style="display: none;" onclick="displayAllMessages()">Type '!' to return to all messages.</div>

            <!-- Print the bottom of the page to auto scroll there -->
            <span class="bottom" id="bottom"></span>
        </div>

        <!-- Display the message bar at the bottom of the page -->
        <div class="message-container">
            <textarea id="message-box" placeholder="Say something..." name="message" autocomplete="off" autofocus></textarea>
            <button id="send-button" type="submit" onclick="postMessage()" disabled>Send!</button>
        </div>

        <!-- Settings modal -->
        <div id="settings-modal">
            <h2>Options</h2>
            <span id="settings-modal-close" onclick="document.getElementById('settings-modal').style.display='none';">X</span>
            <input type="checkbox" id="autoscroll-option" onclick="localStorage.autoscroll = this.checked; console.log(this.checked)"> Automatically scroll to bottom when new messages are posted.<br>
            <input type="checkbox" id="autofocus-option" onclick="localStorage.autofocus = this.checked;"> Automatically focus the message box upon keypress or click.<br>
            <input type="checkbox" id="disable-markdown-option" onclick="localStorage.disable_markdown = this.checked;"> Disable markdown rendering (decreases loading time).<br>
        </div>

        <script>
            window.onload = function() {
    
                // Autofocus the messaging box ("autofocus" does not work)
                document.getElementById("message-box").focus();
                enableSubmit();
            }

            // Only allow settings to be available if they can be stored
            if (typeof(Storage) !== "undefined") {
                document.getElementById("welcome-message").style.cursor = "pointer";
            }

            function openSettings() {
                let settingsModal = document.getElementById("settings-modal");

                if (typeof(Storage) !== "undefined") {
                    
                    if (localStorage.autoscroll) {
                        document.getElementById("autoscroll-option").checked = (localStorage.autoscroll == "true");
                    } 
                    if (localStorage.autofocus) {
                        document.getElementById("autofocus-option").checked = (localStorage.autofocus == "true");
                    }
                    if (localStorage.disable_markdown) {
                        document.getElementById("disable-markdown-option").checked = (localStorage.disable_markdown == "true");
                    }
                    settingsModal.style.display = "block";
                }
            }

            // Timeout for going back after a search (Is there a better way of doing this?)
            var globalGoBackTimeout = null;
            var globalGoBackCountTimeout = null;

            var checkForMessages = true;

            // Every 2 seconds, update the messages display
            var updateInterval = 2; // In seconds
            updateMessages();
            setInterval(updateMessages, updateInterval * 1000);

            var deviceName = "<?php echo cleanString($_COOKIE[COOKIE_NAME]) ?>";
            var latestMessageTime = 0;
            var firstMessage = true;
            var allMessagesJSON = [];   // Local copy of the messages
            function updateMessages() { 
                if (checkForMessages) {
                    const messageData = new FormData();
                    messageData.append('update-time', latestMessageTime);

                    fetch('messenger.php', {
                        method: "POST",
                        body: messageData
                    })
                    .then(x => x.text())
                    .then(result => {
                        if (result != "") {   // Got messages
                            let responseArray = JSON.parse(result);
                            for (let i in responseArray) { // Display the messages
                                let message = responseArray[i];
                                let messageTime = message.time;
                                if (messageTime > latestMessageTime) {
                                    latestMessageTime = messageTime;
                                    let messageKey = message.<?php echo MESSAGE_ID_KEY ?>;
                                    document.getElementById("chat-sub-container").innerHTML += jsonMessageToHtml(i, messageKey, message);
                                    showNotification();
                                    allMessagesJSON.push(message);

                                    if (typeof(Storage) !== "undefined" && localStorage.autoscroll == "true") {
                                        scrollToBottom();
                                        console.log("Scrolling up top");
                                    }
                                }
                            };
                        
                            document.getElementById("loading-msg").style.display = "none";

                            if (allMessagesJSON.length > 0) {
                                document.getElementById("first-person-message").style.display = "none";
                            }
                            else {
                                document.getElementById("first-person-message").style.display = "inherit";
                            }
                            
                            if (firstMessage == true) {
                                scrollToBottom();
                                firstMessage = false;
                            }
                        }
                    })
                    .catch(error => {
                        console.log("Error: ", (error));
                        // alert("There was a problem downloading the messages:\n", error);
                    });
                }
            }

            /**
                * Generate the html form of a message
                *
                * @param messageIndex The index of the message in the array of all messages
                * @param messageKey The unique ID number for the message being generated
                * @param message The array representing the message to be displayed
                * @return A string containing the HTML to properly display the message
                */
            function jsonMessageToHtml(messageIndex, messageKey, messageData) {
                    // Generate the HTML for the message's tags
                let tagsHtml = "";
                let tags = messageData["<?php echo TAGS_KEY ?>"];
                for (let tagi in tags) {
                    let tag = tags[tagi];
                    let color = genColor(tag);
                    let tagUrl = encodeURI(tag);
                    tagsHtml += "<span class='tags' onclick='search(\"" + tag + "\")' style='border-color:#" + color + "'>#" + tag + "</span>";
                }

                let date = new Date(messageData["<?php echo TIME_KEY ?>"] * 1000);
                let month = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][date.getMonth()];
                let dom = date.getDate();
                dom = dom < 10 ? "0" + dom : dom;
                let day = ["Mon", "Tues", "Wed", "Thu", "Fri", "Sat", "Sun"][date.getDay()];
                let minute = date.getMinutes();
                minute = minute < 10 ? "0" + minute : minute;
                let hour = date.getHours();
                let timeSuffix = hour < 12 ? "AM" : "PM";
                hour = hour % 12; // 12-hour time
                hour = hour ? hour : 12; // 0 = 12 o'clock

                let formattedTime = day + ", " + month + " " + dom + " - " + hour + ":" + minute + " " + timeSuffix;

                let messageHTML =  messageData["<?php echo MESSAGE_KEY ?>"]
                
                if (typeof(Storage) !== "undefined" && localStorage.disable_markdown == "false") {
                    messageHTML = messageHTML.replace(/@[a-z0-9\-_\.]+/gi, function (str) {
                        return "<span class='mention'>" + str + "</span>";
                    })
                    .replace(/`(\\.|[^\`]){1,}`/g, function (str) {
                        return "<code>" + str.substr(1, str.length - 2) + "</code>";
                    })
                    .replace(/\*\*(\\.|[^\*]){1,}\*\*/g, function (str) {
                        return "<b>" + str.substr(2, str.length - 4) + "</b>";
                    })
                    .replace(/\*(\\.|[^\*]){1,}\*/g, function (str) {
                        return "<i>" + str.substr(1, str.length - 2) + "</i>";
                    })
                    .replace(/~~(\\.|[^\~\n])+~~/g, function (str) {
                        return "<strike>" + str.substr(2, str.length - 4) + "</strike>";
                    })
                    .replace(/__(\\.|[^\_]){1,}__/g, function (str) {
                        return "<u>" + str.substr(2, str.length - 4) + "</u>";
                    });
                }

                // Generate the HTML for the entire message
                return "\
                    <div class='single-message' id='" + messageKey + "'>\
                        <hr>\
                        <p class='name'>" + messageData["<?php echo DEVICE_KEY ?>"] + "</p>\
                        <p class='time'>" + formattedTime + "</p>\
                        <div class='tags-container'>" + tagsHtml + "</div>\
                        <button onclick='deleteMessage(" + messageIndex + "," + messageKey + ")' class='del-button'>Delete</button>\
                        <div class='message' onclick='copyMessage()' id='" + messageKey + "-m'>" + messageHTML + "</div>\
                        <br>\
                    </div>";
            }

            /** 
                * Generate a color hex triplet based on the input text seed
                *
                * @param $text The text to be used as the seed for the color generator
                * @return A string that is the seeded hex color triplet based off the input string
                */
            function genColor(text) {
                let hash = jsHashString(text);
                let hashSection = (hash & 0xff);
                var color = ((25 * jsHashString(hashSection.toString())) & 0x0000ff00) << 8;
                color += (13 * hashSection) << 8;
                color += 0x80 - hashSection;
                color = (color & 0x00ffffff).toString(16);
                while (color.length < 6) color = "0" + color;   // Hex color is 6 chars long
                return color;
            }

            function jsHashString(str) {
                let hash = 0;
                for (let i = 0; i < str.length; i++) {
                    hash += str.charCodeAt(i) * 41;
                }
                return hash & 0xffffffff;
            }

            // Show popup in corner indicating that there is a new message
            function showNotification() {
                document.getElementById("message-notifier").style.display = "block";
                setTimeout(hideNotification, 5000); // 5 seconds
            }

            // Hide notification bubble for new messages
            function hideNotification() {
                document.getElementById("message-notifier").style.display = "none";
            }

            // Scroll to bottom of page
            function scrollToBottom() {
                document.getElementById("bottom").scrollIntoView({behavior: "auto", block: "end", inline: "nearest"});  // Go to bottom
                let foo = document.getElementById("bottom");
                foo.scrollBottom = foo.scrollHeight;
                hideNotification();
            }

            // Copy message on click
            function copyMessage() {
                let target = event.target || event.srcElement;
                copyArea = document.createElement('textarea');
                copyArea.value = document.getElementById(target.id).textContent.trim();
                console.log(copyArea.value);
                // The following does work, but previously gave a "Storage access automatically granted" warning
                if (event.ctrlKey) { // Ctrl+click to open in new tab/window
                    window.open(copyArea.value, '_blank').focus;
                }
                copyArea.setAttribute('readonly', '');
                copyArea.style;
                document.body.appendChild(copyArea);
                copyArea.select();
                document.execCommand('copy');
                document.body.removeChild(copyArea);
            }

            // Script for the submitt button to make sure that it does not submit a blank message
            function enableSubmit() { 
                let button = document.getElementById("send-button");
                if (document.getElementById("message-box").value != "") {
                    button.disabled = false; 
                } else { 
                    button.disabled = true; 
                } 
            }

            function postMessage() {
                let postButton = document.getElementById("send-button");
                let messageBox = document.getElementById("message-box");
                let messageBoxValue = messageBox.value.trim();
                if (postButton.disabled === false && messageBoxValue != "") {

                    if (messageBoxValue.charAt(0) == "!") { // It's a search
                        messageBox.value = "";  // Clear message
                        let searchQuery = messageBoxValue.replace(/[\s].*/, "").substr(1);
                        if (searchQuery != "") {
                            checkForMessages = false;
                            search(searchQuery);
                        } else if (!checkForMessages) { // checkForMessages basically indicates if we are not in search mode
                            displayAllMessages();
                            checkForMessages = true;
                        }
                    } else {    // Post message
                        if (!checkForMessages) { // If in search mode, exit and display all messages
                            displayAllMessages();
                            checkForMessages = true;
                        }

                        const messageData = new FormData();
                        messageData.append('message', messageBoxValue);
                        messageData.append('js', null); // Indicate that the message was from the page's script
                        postButton.disabled = true;

                        fetch('messenger.php', {
                            method: 'POST',
                            body: messageData
                        })
                        .then(result => { 
                            console.log('Success: ', result); 
                            messageBox.value = "";  // Clear message
                        })
                        .catch(error => {
                            console.log("Error: ", error);
                            alert("There was a problem posting your message:\n" + error);
                            postButton.disabled = false;
                        });
                    }
                }
            }

            function deleteMessage(messageIndex, messageId) {
                if (confirm("Are you sure you want to delete this message?")) {
                    const deleteData = new FormData();
                    deleteData.append('delete-message', "");
                    deleteData.append('message-id', messageId);
                    fetch('messenger.php', {
                        method: 'POST',
                        body: deleteData
                    })
                    .then(result => {
                        console.log("Message: '" + messageId + "' has been deleted");
                        document.getElementById(messageId).remove();    // Remove from user's view
                        allMessagesJSON.splice(messageIndex, 1);
                    })
                    .catch(error => {
                        console.log("Error: ", error);
                        alert("There was a problem deleting message '" + messageId + "'\n");
                    });
                }
            }

            function search(searchString) {

                searchString = searchString.toUpperCase();

                let resultCount = 0;

                document.getElementById("chat-sub-container").innerHTML = "";
                let messagesContainer = document.getElementById("chat-sub-container");

                if (searchString != "") {
                    // Go through the tags for all the messages, printing the ones that match the 
                    // search query as we go along.
                    for (let i in allMessagesJSON) {
                        let message = allMessagesJSON[i];
                        let messageTags = message["<?php echo TAGS_KEY ?>"];
                        let messageHasTag = false;
                        console.log(message);

                        for (let j in messageTags) {
                            console.log(messageTags[j]);

                            // Check if search string is part of the message's tag(s)
                            if (!messageHasTag && messageTags[j].toUpperCase().includes(searchString)) {

                                // Display message
                                let messageId = message["<?php echo MESSAGE_ID_KEY ?>"];
                                messagesContainer.innerHTML += jsonMessageToHtml(i, messageId, message);

                                resultCount += 1;

                                // Make sure that the message does not get displayed more than once
                                messageHasTag = true;
                            }
                        }
                    }
                }

                if (resultCount == 0) { // No results!
                    let box = document.getElementById("no-messages");
                    let tagBox = document.getElementById("no-messages-tag");
                    tagBox.style.borderColor = "#" + genColor(searchString);
                    tagBox.innerHTML = searchString;
                    box.style.display = "inherit";

                    var timeout = 5; // Seconds
                    clearTimeout(globalGoBackTimeout);  // Wouldn't want multiple timeouts!
                    globalGoBackTimeout = setTimeout(goBack, timeout * 1000 - 500);
                    
                    function goBack() {
                        displayAllMessages();
                        box.style.display = "none";
                    }

                    clearTimeout(globalGoBackCountTimeout);
                    countDown();
                    function countDown() {
                        document.getElementById('count-block').innerHTML = timeout;
                        timeout -= 1;
                        if (timeout > 0) 
                            globalGoBackCountTimeout = setTimeout(countDown, 1000);
                    }
                } else {
                    document.getElementById("yes-messages").style.display = "block";
                }
                document.getElementById("message-box").focus();
            }

            function displayAllMessages() {
                let chatContainer = document.getElementById("chat-sub-container");
                chatContainer.innerHTML = "";
                document.getElementById("yes-messages").style.display = "none";
                document.getElementById("no-messages").style.display = "none";

                for (let i in allMessagesJSON) { // Display the messages
                    let message = allMessagesJSON[i];
                    let messageKey = message["<?php echo MESSAGE_ID_KEY ?>"];
                    chatContainer.innerHTML += jsonMessageToHtml(i, messageKey, message);
                };
                if (allMessagesJSON.length > 0) {
                    document.getElementById("first-person-message").style.display = "none"; 
                }
                window.location.hash = "bottom";
                document.getElementById("message-box").focus();
                checkForMessages = true;
            }

            document.addEventListener('keypress', keyHandler);
            document.addEventListener('click', keyHandler);

            function keyHandler(e) {
                
                enableSubmit();

                if (typeof(Storage) !== "undefined" && localStorage.autofocus == "true") {
                    document.getElementById("message-box").focus();
                }

                if (e.ctrlKey && e.keyCode == 13) {  // Ctrl + Enter
                    postMessage();
                }
            }
            </script>
<?php   } // End 'else' ?>
</body>
</html>
