<?php
    const COOKIE_NAME = "device";
    const DEVICE_KEY  = "device";
    const MESSAGE_KEY = "message";
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

    /** 
     * Generate a color hex triplet based on the input text seed
     *
     * @param $text The text to be used as the seed for the color generator
     * @return A string that is the seeded hex color triplet based off the input string
     */
    function genColor($text) {
        $crc = crc32($text);
        $crc_section = ($crc & 0xff000000) >> 24;
        $color = (25 * crc32($crc_section)) & 0x00ff0000;
        $color += (13 * $crc_section) << 8;
        $color += 0x80 - $crc_section;
        return str_pad(dechex($color & 0x00ffffff), 6, "0", STR_PAD_LEFT);
    }

    /**
     * Generate the html form of a message
     *
     * @param $message_id The unique ID number for the message being generated
     * @param $message The array representing the message to be displayed
     * @return A string containing the HTML to properly display the message
     */
    function generateMessageHTML($message_id, $message) {

        // Generate the HTML for the message's tags
        $tags_html = "";
        foreach ($message[TAGS_KEY] as $tag) {
            $color = genColor($tag);
            $tag_url = htmlspecialchars(rawurlencode($tag));
            $tags_html .= "<a href='?f={$tag_url}'><span class='tags' style='border-color:#{$color}'>#{$tag}</span></a>";
        }

        // Generate the HTML for the entire message (TODO: Maybe remove a lot of this whitespace (indentation) to reduce bandwidth?)
        return "
            <div class='single-message' id='{$message_id}'>
                <hr>
                <p class='name'>{$message[DEVICE_KEY]}</p>
                <p class='time'>".date('D, M d - h:i A', $message[TIME_KEY])."</p>
                <div class='tags-container'>{$tags_html}</div>
                <button onclick='deleteMessage({$message_id})' class='del-button'>Delete</button>
                <div class='message' onclick='copyMessage()' id='{$message_id}-m'>".nl2br($message[MESSAGE_KEY])."</div>
                <br>
            </div>";
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" ) {
        if(isset($_POST['submitt-button']) && !isset($_COOKIE[COOKIE_NAME])){ //check if form was submitted and add the user's cookie
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

            // Make the message with a random, unique key
            $key = rand();
            while (in_array($key, $messages_array)) {
                $key = rand();
            }

            // Generate the tags array and strip leading tags
            $tag_identifier = "#";
            $filter_identifier = "!";

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
                        // If the filter character is the beginning of the token, do not save this message and instead only
                        // return the messages that contain the filter string
                        if ($still_at_front && $token[0] == $filter_identifier && strlen($token) > 1) {
                            $message_filter .= substr($token, 1);    // Remove the filter identifier
                        }
                        $still_at_front = false;    // No longer at front of message, stop counting tag length
                    }
                }
            }
            if ($removal_length != 0) {
                $message = substr($message, $removal_length - 1);   // -1 just in case there is no space after the last tag            
            }
            // $message = ltrim($message);
            

            if ($message != "" && $message != $filter_identifier && $message_filter == "") {   // Would not want a bunch of empty messages! (and do not add searches)
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

            if (isset($_POST["js"])) {
                exit(0);  // Exit, do not display webpage (if "js" is set, it (probably) means that the user POSTed a message via the page's built-in JS)
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
        }
        
        $header = "location: messenger.php";

        if ($message_filter != "") {
            $header .= "?f=".rawurlencode($message_filter);
        } 
        header($header);
    }

    // Update if another message is posted
    if ($_SERVER["REQUEST_METHOD"] == "GET") {

        // If there is an update interval
        if (isset($_GET['t']) && isset($_GET['d'])) {

            $time = cleanString($_GET['t']);
            $device = cleanString($_GET['d']);

            $data = file_get_contents(MESSAGE_FILE);
            $data_array = json_decode($data, true);

            $return_messages = [];

            // Print all the messages
            foreach ($data_array as $message_id => $message) {
                if ($message[TIME_KEY] > $time) {
                    $return_messages[] = array(
                        "html" => generateMessageHTML($message_id, $message),
                        DEVICE_KEY => $message[DEVICE_KEY],
                        "time" => $message[TIME_KEY]
                    );
                } 
            }
            echo json_encode($return_messages);
            exit(0);  // Exit, do not display webpage
        }
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
                    <button name="submitt-button" type="submit" class="signupbtn">Sign Up</button>
                </div>
            </div>
        </form>
    <?php } else { ?>    
        <!-- Display the "menu" bar -->
        <div class="navbar">
            <Strong>Messenger</strong>
            <i>Welcome <?php echo cleanString($_COOKIE[COOKIE_NAME]) ?></i>
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

            <div id="chat-container">

                <!-- Set to check for messages incomming while the page is open -->
                <script>
                    var checkForMessages = true;
                </script>

                <!-- Draw the messages -->
                <?php
                    $data = file_get_contents(MESSAGE_FILE);
                    $data_array = json_decode($data, true);

                    // Print all the messages
                    if (!$data_array) { ?>
                        <div id='empty-message-container'>
                            Looks like you're the first one here!<br>
                            Type a message to get started.<br>
                            You can tag messages with '#' at the beinning of keywords<br>
                            and search by tags with a '!'.<br><br>
                            (Make sure that tags and searches are at the beginning of the message :)
                        </div>
                    <?php } else {
                        if (isset($_GET['f'])) {    // Search / find 
                            echo "<script>checkForMessages = false;</script>";  // Disable checking for new messages
                            
                            $result_count = 0;
                            
                            // What we are searching for
                            $message_filter = strtoupper(cleanString(rawurldecode($_GET['f'])));

                            if ($message_filter) {
                                // Go through the tags for all the messages, printing the ones that match the 
                                // search query as we go along.
                                foreach ($data_array as $message_id => $message) {
                                    $message_has_tag = false;
                                    foreach ($message[TAGS_KEY] as $tag) {
                                        
                                        // Check if search string is part of the message's tag(s)
                                        if (!$message_has_tag && strpos(strtoupper($tag), $message_filter) !== FALSE) {

                                            // Display Message
                                            echo generateMessageHTML($message_id, $message);
                                            $result_count++;

                                            // Make sure that the message does not get displayed more than once
                                            $message_has_tag = true;
                                        }
                                    }
                                }
                            }

                            if ($result_count == 0) { // No results! ?>
                                <div id='empty-message-container'>
                                    <span class='tags' style='margin: 10px; border-color:#<?php echo genColor($message_filter) ?>'> <?php echo $message_filter ?></span><br>
                                    Looks like no one has posted with that tag!<br>
                                    Returning ... <span id='count-block'></span>
                                </div>
                                <script>
                                    var timeout = 5;
                                    setTimeout(function () {
                                        window.location.href = 'messenger.php';
                                    }, timeout * 1000 - 500);
                                    
                                    countDown();
                                    function countDown() {
                                        document.getElementById('count-block').innerHTML = timeout;
                                        timeout -= 1;
                                        setTimeout(countDown, 1000);
                                    }
                                </script>
                            <?php } else { // YAY! Results, remind the user how to get back to all messages. 
                                echo "<div id='empty-message-container'><a href='messenger.php'>Type '!' to return to all messages.</a></div>";
                            }
                        }
                    }
                ?>
            </div>
            
            <!-- Print the bottom of the page to auto scroll there -->
            <span class="bottom" id="bottom"></span>
        </div>

        <!-- Display the message bar at the bottom of the page -->
        <div class="message-container">
            <textarea id="message-box" placeholder="Say something..." name="message" autocomplete="off" autofocus></textarea>
            <button id="send-button" type="submit" onclick="postMessage()" disabled>Send!</button>
        </div>

        <script>
            window.onload = function() {
                
                // Autofocus the messaging box ("autofocus" does not work)
                document.getElementById("message-box").focus();
                enableSubmit();
            }

            // Every 2 seconds, update the messages display
            var updateInterval = 2; // In seconds
            if (checkForMessages == true) {
                updateMessages();
                setInterval(updateMessages, updateInterval * 1000);
            }
            var deviceName = "<?php echo cleanString($_COOKIE[COOKIE_NAME]) ?>";
            var latestMessageTime = 0;
            var firstMessage = true;
            function updateMessages() { 
                let httpRequest = new XMLHttpRequest();
                httpRequest.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        if (httpRequest.responseText != "") {   // Got messages
                            let responseArray = JSON.parse(httpRequest.responseText);
                            for (let messageKey in responseArray) { // Display the messages
                                let message = responseArray[messageKey];
                                document.getElementById("chat-container").innerHTML += message.html;
                                document.getElementById("loading-msg").style.display = "none";
                                showNotification();
                                let messageTime = message.time;
                                if (messageTime > latestMessageTime) {
                                    latestMessageTime = messageTime;
                                }
                                // window.location.hash=responseArray.id;
                            };
                            if (firstMessage == true) {
                                scrollToBottom();
                                firstMessage = false;
                            }
                        }
                    }
                };
                httpRequest.open("GET", "messenger.php?t=" + latestMessageTime + "&d=" + deviceName, true);
                httpRequest.send();
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
                if (postButton.disabled === false && messageBox.value.trim() != "") {
                    const messageData = new FormData();
                    messageData.append('message', messageBox.value);
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

            function deleteMessage(messageId) {
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
                    })
                    .catch(error => {
                        console.log("Error: ", error);
                        alert("There was a problem deleting message '" + messageId + "'\n");
                    });
                }
            }

            document.addEventListener('keyup', keyHandler);

            function keyHandler(e) {
                
                enableSubmit();

                // document.getElementById("message-box").focus();

                if (e.ctrlKey && e.keyCode == 13) {  // Ctrl + Enter
                    postMessage();
                }
            }

            </script>
<?php   } // End 'else' ?>
</body>
</html>
