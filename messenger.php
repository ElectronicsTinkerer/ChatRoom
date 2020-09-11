<?php
    $cookie_name = "device";
    $device_key  = "device";
    $message_key = "message";
    $time_key    = "time";
    $tags_key    = "tags";
    $message_file = "messenger.json";

    // Flags
    $message_filter = "";

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
        global $device_key;
        global $time_key;
        global $message_key;
        global $tags_key;

        // Generate the HTML for the message's tags
        $tags_html = "";
        foreach ($message[$tags_key] as $tag) {
            $color = genColor($tag);
            $tags_html .= "<span class='tags' style='border-color:#{$color}'>{$tag}</span>";
        }

        // Generate the HTML for the entire message (TODO: Maybe remove a lot of this whitespace (indentation) to reduce bandwith?)
        return "
            <div class='single-message'>
                <hr>
                <p class='name'>{$message[$device_key]}</p>
                <p class='time'>".date('D, M d - h:i A', $message[$time_key])."</p>
                <div class='tags-container'>{$tags_html}</div>
                <form action='' method='post' class='inline-form'>
                    <input type='text' value='{$message_id}' name='message-id' style='display: none;'>
                    <input type='submit' name='delete-message' value='Delete'>
                </form>
                <div class='message' onclick='copyMessage()' id='{$message_id}'>
                    {$message[$message_key]} 
                </div>
                <br>
            </div>";
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if(isset($_POST['submitt-button']) && !isset($_COOKIE[$cookie_name])){ //check if form was submitted and add the user's cookie213215761546995
            $cookie_value = htmlspecialchars(stripslashes(trim($_POST['device']))); //get input text
            setcookie($cookie_name, $cookie_value, time() + (86400 * 365), "/");
            header("refresh:0");
        } elseif (isset($_POST['logout'])) { // Delete the user's cookie (log them out)
            unset($_COOKIE[$cookie_name]);
            setcookie($cookie_name, "", time() - 3600, '/');
            header("refresh:0");
        } elseif (isset($_POST['message']) && $_POST[$message_key] != "") { // Post message to server if not empty
            // Get the messages
            $messages = file_get_contents($message_file);
            $messages_array = json_decode($messages, true);

            // Make the message with a random, unique key
            $key = rand();
            while (in_array($key, $messages_array)) {
                $key = rand();
            }

            // Generate the tags array and strip leading tags
            $delimiter = " ";
            $tag_identifier = "#";
            $filter_identifier = "!";

            $message = trim($_POST[$message_key]);
            $tokens = explode($delimiter, $message);
            $tags = [];
            $removal_length = 0;
            $still_at_front = true;     // Indicates that the tags are still at the front of the string and the length should be counted for removal
            foreach ($tokens as $token) {

                if ($token != "") {     // Ignore double (or triple or quadruple or pentup--...) spaces
                    if ($token[0] == $tag_identifier) {
                        echo $token;
                        $tags[] =  htmlspecialchars(stripslashes(trim($token)));
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

            if ($message != "" && $message != $filter_identifier && $message_filter == "") {   // Would not want a bunch of empty messages! (and do not add searches)
                // JSON data entry
                $message_data = [ $key => array(
                    $time_key => time(),
                    $device_key => htmlspecialchars(stripslashes(trim($_COOKIE[$cookie_name]))),
                    $message_key => htmlspecialchars(stripslashes(trim($message))),
                    $tags_key => $tags
                )];

                // Append the message
                $messages_array += $message_data;

                // Return to json and put updated to our file
                $messages_array = json_encode($messages_array, JSON_PRETTY_PRINT);
                file_put_contents($message_file, $messages_array);
            }
        } elseif (isset($_POST['delete-message'])) {
            // Get message
            $messages = file_get_contents($message_file);
            $messages_array = json_decode($messages, true);

            // Delete the message
            unset($messages_array[htmlspecialchars(stripslashes(trim($_POST['message-id'])))]);

            // Return to json and put updated to our file
            $messages_array = json_encode($messages_array, JSON_PRETTY_PRINT);
            file_put_contents($message_file, $messages_array);
        }
        
        $header = "location: messenger.php";

        if ($message_filter != "") {
            $header .= "?f=".$message_filter;
        } 
        header($header);
    }

    // Update if another message is posted
    if ($_SERVER["REQUEST_METHOD"] == "GET") {

        // If there is an update interval
        if (isset($_GET['t']) && isset($_GET['d'])) {

            $time = htmlspecialchars(stripslashes(trim($_GET['t'])));
            $device = htmlspecialchars(stripslashes(trim($_GET['d'])));

            $data = file_get_contents($message_file);
            $data_array = json_decode($data, true);

            $return_messages = [];

            // Print all the messages
            foreach ($data_array as $message_id => $message) {
                if ($message[$time_key] > $time) {
                    $return_messages[] = array(
                        "html" => generateMessageHTML($message_id, $message),
                        "time" => $message[$time_key]
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
    <meta content="text/html;charset=utf-8" http-equiv="Content-Type">
    <meta content="utf-8" http-equiv="encoding">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>

        html {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 17px;
            background-color: #333;
            margin: 0;
            padding: 0;
        }

        * {
            box-sizing: border-box;
        }

        /* Full-width input fields */
        input[type=text]:not(#message-box) {
            width: 100%;
            padding: 15px;
            margin: 5px 0 22px 0;
            display: inline-block;
            border: none;
            background: #f1f1f1;
        }

        /* Add a background color when the inputs get focus */
        input[type=text]:focus {
            background-color: #fff;
            outline: none;
        }

        /* Set a style for all buttons */
        button:not(.hidden) {
            background-color: #3399ff;
            color: white;
            padding: 14px 20px;
            margin: 8px 0;
            border: none;
            cursor: pointer;
            width: 100%;
            opacity: 0.9;
            font-size: 17px;
        }

        .signupbtn {
            float: right;
            width: 50%;
        }

        /* Add padding to container elements */
        .container {
            padding: 16px;
        }

        /* Modal Content/Box */
        .modal-content {
            background-color: #e0e0e0;
            display: block;
            margin-left: auto;
            margin-right: auto;
            border: 2px solid black;
            width: 50%; /* Could be more or less, depending on screen size */
            min-width: 300px;
        }


        /* Clear floats */
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* Change styles for cancel button and signup button on extra small screens */
        @media screen and (max-width: 300px) {
            .signupbtn {
                width: 100%;
            }
        }

        /* NAV BAR / HEADER THING */
        .navbar {
            overflow: hidden;
            background-color: #333;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1;
        }

        .navbar form, .navbar strong, .navbar i {
            display: block;
            color: #f2f2f2;
            text-align: center;
            text-decoration: none;
        }

        .navbar form {
            float: right;
            margin-right: 20px;
        }

        .navbar strong {
            font-size: 20px;
            font-weight: bold;
            float: center;
            padding: 20px;
            margin: 0 0 -40px 0;
            height: 40px;
        }

        .navbar i {
            float: left;
            padding: 20px;
        }

        /* BOTTOM MESSAGE BAR */
        .message-container {
            overflow: hidden;
            background-color: #333;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
        }

        #message-box {
            padding: 15px;
            margin: 12px 0 10px 10px;
            display: inline;
            border: none;
            background: #f1f1f1;   
            overflow: hidden;
            width: calc(100% - 115px); 
            font-size: inherit;
        }

        .notification {
            position: absolute;
            right: 5vw;
            bottom: 100px;
            background-color: #333;
            font-family: Operator Mono A,Operator Mono B,Source Code Pro,Menlo,Consolas,Monaco,monospace;
            text-align: center;
            padding: 20px;
            color: white;
            border: 3px solid red;
            border-radius: 10px;
            cursor: pointer;
            display: none;
        }

        button.hidden {
            background-color: #3399ff;
            color: white;
            padding: 15px 20px;
            margin: 12px 10px 10px 0;
            border: none;
            cursor: pointer;
            opacity: 0.9;
            float: right;
            display: inline-block;
            font-size: inherit;
        }

        button:hover { /* Also needed for logout and signup */
            background-color: darkgreen;
        }

        button.hidden:disabled {
            background-color: grey;
            cursor: not-allowed;
        }

        /* CHAT CONTAINER - where the messages are displayed */
        .chat-container {
            margin: 53px 10px 53px 10px;
            height: calc(100vh - (53px * 2));
            overflow-y: scroll;
            overflow-x: hidden;
            background-color: black;
            word-wrap: break-word; /* For Mitchel */
            /* padding: 0 0 50px 0; */
            /* position: fixed; */
        }

        /* Small screens */
        @media only screen and (max-height: 600px) {
            .chat-container {
                height: 70vh;
            }
        }

        .bottom {
            height: 0px;
            margin: 20px;
        }

        /* Holds the messages */
        .chat-container .name {
            color: #30ce2d;
            display: inline-block;
            margin-left: 10px;
        }

        .chat-container .time {
            color: #c0c0e0;
            display: inline-block;
            margin-left: 10px;
            font-size: 13px;
        }

        .chat-container .message {
            color: white;
            margin: 0 10px 0 10px;
            cursor: copy;
        }

        .chat-container hr {
            margin-left: 5px;
            margin-right: 5px;
        }

        /* Tag styling */
        .tags {
            display: inline-block;
            border-radius: 100px;
            border: 3px solid;
            color: white;
            /* text-shadow: 0 0 5px black; */
            font-family: "Times New Roman", Times, serif;
            padding: 3px 7px;
            margin: 0 0 3px 10px;
        }

        .tags-container {
            display: inline-block;
            margin: 0 0 10px 0;
            padding: 0;
        }

        /* Delete button */
        .inline-form {
            display: inline-block;
        }

        input[type="submit"] {
            background-color: red;
            border: 1px solid grey;
            color: white;
            margin-left: 10px;
            cursor: pointer;
            visibility: hidden;
        }

        input[type="submit"]:hover {
            background-color: darkred;
        }

        .single-message:hover .inline-form input[type="submit"] { 
            visibility: visible;
        }

        /* Message for "no messages" */
        #empty-message-container {
            font-family: Operator Mono A,Operator Mono B,Source Code Pro,Menlo,Consolas,Monaco,monospace;
            font-style: italic;
            text-align: center;
            padding: 30px;
            color: white;
        }

        @media only screen and (max-width: 550px) {
            .navbar strong {
                display: none;
            }

            #logout-button {
                padding: 10px;
            }

            .notification {
                padding: 10px;
            }

            /* See below for .tags and .tags-container */
            .tags-container {
                display: block;
                margin: -10px 0 5px 0;
            }
        }
    </style>
</head>
<body>
<?php
    // Setup the cookies to know which device is sending the message
    if(!isset($_COOKIE[$cookie_name])) { ?>
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
            <i>Welcome <?php echo htmlspecialchars($_COOKIE[$cookie_name]) ?></i>
            <form action="" method="post">
                <button name="logout" id="logout-button">Logout</button>
            </form>
        </div>

        <!-- Chat container -->
        <div class="chat-container">
    
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
                    $data = file_get_contents($message_file);
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
                            echo "<script>checkForMessages = false;</script>";
                            $result_count = 0;
                            $message_filter = "#".htmlspecialchars(stripslashes(trim($_GET['f'])));
                            foreach ($data_array as $message_id => $message) {
                                if (in_array($message_filter, $message[$tags_key])) {
                                    echo generateMessageHTML($message_id, $message);
                                    $result_count++;
                                }
                            }
                            if ($result_count == 0) { ?>
                                <div id='empty-message-container'>
                                    <span class='tags' style='margin: 10px; border-color:#".genColor($message_filter)."'> <?php echo $message_filter ?></span><br>
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
                            <?php } else {    
                                echo "<div id='empty-message-container'>Type '!' to return to all messages.</div>";
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
            <form action="" method="post">
                <input id="message-box" type="text" placeholder="Say something..." name="message" autocomplete="off" oninput="enableSubmit()" autofocus/>
                <button id="send-button" class="hidden" type="submit" disabled>Send!</button>
            </form>
        </div>

        <script>
            // Autofocus the messaging box ("autofocus" does not work)
            window.onload = function() {
                document.getElementById("message-box").focus();
            }

            // Every 2 seconds, update the messages display
            var updateInterval = 2; // In seconds
            if (checkForMessages == true) {
                updateMessages();
                setInterval(updateMessages, updateInterval * 1000);
            }
            var deviceName = "<?php echo htmlspecialchars(stripslashes(trim($_COOKIE[$cookie_name]))) ?>";
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
                setTimeout(hideNotification, 5000);
            }

            // Hide notification bubble for new messages
            function hideNotification() {
                document.getElementById("message-notifier").style.display = "none";
            }

            // Scroll to bottom of page smoothly
            function scrollToBottom() {
                document.getElementById("bottom").scrollIntoView({behavior: "smooth", block: "end"});  // Go to bottom
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

            </script>
<?php   } // End 'else' ?>
</body>
</html>
