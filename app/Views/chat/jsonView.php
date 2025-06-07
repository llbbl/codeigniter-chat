<!DOCTYPE html>
<html>
<head>
    <title>CodeIgniter Shoutbox - JSON edition</title>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <script type="text/javascript" src="<?= base_url('js/jquery-1.4.2.min.js') ?>"></script>
    <script type="text/javascript">
        $(document).ready(function(){

            loadMsg();            
            hideLoading();

            $("form#chatform").submit(function(){
                // Clear previous errors
                $(".error").text("");
                $("input").removeClass("error-field");

                // Get values
                var name = $("#name").val().trim();
                var message = $("#content").val().trim();
                var isValid = true;

                // Validate name
                if (name === "") {
                    $("#name-error").text("Name is required");
                    $("#name").addClass("error-field");
                    isValid = false;
                } else if (name.length < 2) {
                    $("#name-error").text("Name must be at least 2 characters long");
                    $("#name").addClass("error-field");
                    isValid = false;
                } else if (name.length > 50) {
                    $("#name-error").text("Name cannot exceed 50 characters");
                    $("#name").addClass("error-field");
                    isValid = false;
                } else if (!/^[a-zA-Z0-9 ]+$/.test(name)) {
                    $("#name-error").text("Name can only contain alphanumeric characters and spaces");
                    $("#name").addClass("error-field");
                    isValid = false;
                }

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
                    $.post("<?= site_url('chat/update') ?>", {
                                message: message,
                                name: name,
                                action: "postmsg",
                                <?= csrf_token() ?>: $('meta[name="csrf-token"]').attr('content')
                            }, function(response) {
                        hideLoading();

                        if (response && response.success === false) {
                            // Display server-side validation errors
                            if (response.errors.name) {
                                $("#name-error").text(response.errors.name);
                                $("#name").addClass("error-field");
                            }
                            if (response.errors.message) {
                                $("#content-error").text(response.errors.message);
                                $("#content").addClass("error-field");
                            }
                        } else {
                            // Success - add message to window
                            $("#messagewindow").prepend("<b>"+name+"</b>: "+message+"<br />");

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

        function addMessages(json) {
            //console.log(json);

            $.each(json, function(i,val){
                //console.log(val.id);
                $("#messagewindow").append("<b>"+val.user+"</b>: "+val.msg+"<br />");                
            });
        }

        function loadMsg() {
            // Set up AJAX with CSRF token
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.getJSON("<?= site_url('chat/json_backend') ?>", function(json) {
                $("#loading").remove();                
                addMessages(json);
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
    </style>
</head>
<body>
    <div id="wrapper">
    <p id="messagewindow"><span id="loading">Loading...</span></p>
    <form id="chatform">
    <div id="author">
        Name: <input type="text" id="name" />
        <div id="name-error" class="error"></div>
    </div><br />

    <div id="txt">
        Message: <input type="text" name="content" id="content" value="" />
        <div id="content-error" class="error"></div>
    </div>

    <div id="contentLoading" class="contentLoading">  
        <img src="<?= base_url('images/blueloading.gif') ?>" alt="Loading data, please wait...">  
    </div><br />

    <input type="submit" value="ok" /><br />
    </form>
    </div>
</body>
</html>
