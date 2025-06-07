<!DOCTYPE html>
<html>
<head>
    <title>CodeIgniter Shoutbox</title>
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <script type="text/javascript" src="<?= esc(base_url('js/jquery-1.4.2.min.js')) ?>"></script>
    <script type="text/javascript">
        $(document).ready(function(){

            loadMsg();            
            hideLoading();

            $("form#chatform").submit(function(){
                // Clear previous errors
                $(".error").text("");
                $("input").removeClass("error-field");

                // Get values
                var message = $("#content").val().trim();
                var isValid = true;

                // Validate message
                if (message === "") {
                    $("#content-error").text("Message is required");
                    $("#content").addClass("error-field");
                    isValid = false;
                } else if (message.length > 500) {
                    $("#content-error").text("Message cannot exceed 500 characters");
                    $("#content").addClass("error-field");
                    isValid = false;
                }

                // If validation passes, submit the form
                if (isValid) {
                    showLoading();

                    // Add CSRF token to the request
                    $.post("<?= esc(site_url('chat/update')) ?>", {
                                message: message,
                                action: "postmsg",
                                <?= csrf_token() ?>: $('meta[name="csrf-token"]').attr('content')
                            }, function(response) {
                        hideLoading();

                        if (response && response.success === false) {
                            // Display server-side validation errors
                            if (response.errors.message) {
                                $("#content-error").text(response.errors.message);
                                $("#content").addClass("error-field");
                            }
                        } else {
                            // Success - add message to window
                            // Escape HTML in the message before adding to the DOM
                            var escapedMessage = $('<div/>').text(message).html();
                            $("#messagewindow").prepend("<b><?= esc(session()->get('username')) ?></b>: "+escapedMessage+"<br />");

                            // Clear message field and focus
                            $("#content").val("");                    
                            $("#content").focus();
                        }
                    }, "json").fail(function() {
                        hideLoading();
                        alert("An error occurred while sending your message. Please try again.");
                    });
                }

                return false;
            });


        });

        function showLoading(){
            $("#contentLoading").show();
            $("#txt").hide();
            $("#author").hide();
        }
        function hideLoading(){
            $("#contentLoading").hide();
            $("#txt").show();
            $("#author").show();
        }

        function addMessages(xml) {

            $(xml).find('message').each(function() {

                author = $(this).find("author").text();
                msg = $(this).find("text").text();

                // Escape HTML in the author and message before adding to the DOM
                var escapedAuthor = $('<div/>').text(author).html();
                var escapedMsg = $('<div/>').text(msg).html();

                $("#messagewindow").append("<b>"+escapedAuthor+"</b>: "+escapedMsg+"<br />");
            });

        }

        function loadMsg() {
            // Set up AJAX with CSRF token
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.get("<?= site_url('chat/backend') ?>", function(xml) {
                $("#loading").remove();                
                addMessages(xml);
            });

            //setTimeout('loadMsg()', 4000);
        }
    </script>
    <style type="text/css">
        #messagewindow {
            height: 250px;
            border: 1px solid;
            padding: 5px;
            overflow: auto;
        }
        #wrapper {
            margin: auto;
            width: 438px;
        }
        .error {
            color: red;
            font-size: 0.8em;
            margin-top: 5px;
        }
        .error-field {
            border: 1px solid red;
        }
        #user-info {
            margin-bottom: 10px;
            padding: 5px;
            background-color: #f0f0f0;
            border-radius: 4px;
        }
        .logout-btn {
            float: right;
            color: #d9534f;
            text-decoration: none;
        }
        .logout-btn:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div id="wrapper">
    <div id="user-info">
        Welcome, <b><?= esc(session()->get('username')) ?></b>! 
        <a href="<?= esc(site_url('auth/logout')) ?>" class="logout-btn">Logout</a>
    </div>
    <p id="messagewindow"><span id="loading">Loading...</span></p>
    <form id="chatform">
    <div id="txt">
        Message: <input type="text" name="content" id="content" value="" />
        <div id="content-error" class="error"></div>
    </div>

    <div id="contentLoading" class="contentLoading">  
        <img src="<?= esc(base_url('images/blueloading.gif')) ?>" alt="Loading data, please wait...">  
    </div><br />

    <input type="submit" value="ok" /><br />
    </form>
    </div>
</body>
</html>
